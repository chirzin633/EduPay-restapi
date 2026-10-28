<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Concern\ChecksPeriodClosure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundService
{
    use ChecksPeriodClosure;

    public function createDraft($params)
    {
        $payment = Payment::findOrFail($params['payment_id']);

        if (!$payment->isConfirmed()) {
            throw ValidationException::withMessages([
                'payment_id' => "Hanya pembayaran yang sudah terverifikasi yang bisa direfund. Status saat ini: {$payment->status}",
            ]);
        }

        $maxRefundable = Refund::getMaxRefundableAmount($payment->id);
        if ((float) $params['refund_amount'] > $maxRefundable) {
            throw ValidationException::withMessages([
                'refund_amount' => "Nominal refund melebihi batas yang dapat dikembalikan (Rp" . number_format($maxRefundable, 0, ',', '.') . ").",
            ]);
        }

        $refund = null;

        $refund = DB::transaction(function () use ($params, $payment) {
            $refund = Refund::create([
                'refund_number' => $this->generateRefundNumber(),
                'payment_id' => $payment->id,
                'requested_by' => $params['requested_by'],
                'refund_amount' => $params['refund_amount'],
                'reason' => $params['reason'],
                'refund_method' => $params['refund_method'] ?? 'bank_transfer',
                'recipient_account_name' => $params['recipient_account_name'] ?? null,
                'recipient_account_number' => $params['recipient_account_number'] ?? null,
                'attachment_path' => $params['attachment_path'] ?? null,
                'status' => 'draft'
            ]);

            AuditLog::log(
                action: 'create_refund_draft',
                subject: $refund,
                newValues: [
                    'refund_number' => $refund->refund_number,
                    'amount' => $params['refund_amount'],
                    'payment' => $payment->payment_number
                ],
                reason: $params['reason']
            );

            return $refund;
        });

        return $refund->fresh();
    }

    public function submitForApproval($refundId, $userId)
    {
        $refund = Refund::findOrFail($refundId);

        if ($refund->status !== 'draft') {
            throw ValidationException::withMessages([
                'refund' => "Hanya refund berstatus draft yang bisa diajukan. Status saat ini: {$refund->status}"
            ]);
        }

        if ($refund->requested_by !== $userId) {
            throw ValidationException::withMessages([
                'refund' => 'Hanya pemohon yang dapat mengajukan refund'
            ]);
        }

        DB::transaction(function () use ($refund) {
            $refund->update(['status' => 'pending_approval']);

            AuditLog::log(
                action: 'submit_refund',
                subject: $refund,
                oldValues: ['status' => 'draft'],
                newValues: ['status' => 'pending_approval'],
                reason: null
            );
        });

        return $refund->fresh();
    }

    public function approve($refundId, $approverId, $notes = null)
    {
        $refund = Refund::findOrFail($refundId);

        if ($refund->status !== 'pending_approval') {
            throw ValidationException::withMessages([
                'refund' => "Hanya refund pending_approval yang bisa disetujui. Status: {$refund->status}",
            ]);
        }

        if ($refund->requested_by === $approverId) {
            throw ValidationException::withMessages([
                'approver' => "Anda tidak dapat menyetujui refund yang Anda ajukan sendiri."
            ]);
        }

        DB::transaction(function () use ($refund, $approverId, $notes) {
            $refund->update([
                'status' =>  'approved',
                'approved_by' => $approverId,
                'approved_at' => now(),
                'approval_notes' => $notes
            ]);

            AuditLog::log(
                action: 'approve_refund',
                subject: $refund,
                oldValues: ['status' => 'pending_approval'],
                newValues: ['status' => 'approved', 'approved_by' => $approverId],
                reason: $notes
            );
        });

        return $refund->fresh();
    }

    public function reject($refundId, $rejectorId, $reason)
    {
        $refund = Refund::findOrFail($refundId);

        if ($refund->status !== 'pending_approval') {
            throw ValidationException::withMessages([
                'refund' => "Hanya refund pending_approval yang bisa ditolak. Status: {$refund->status}"
            ]);
        }

        if ($refund->requested_by === $rejectorId) {
            throw ValidationException::withMessages([
                'rejector' => 'Anda tidak dapat menolak refund yang Anda ajukan sendiri.'
            ]);
        }

        DB::transaction(function () use ($refund, $rejectorId, $reason) {
            $refund->update([
                'status' => 'rejected',
                'rejected_by' => $rejectorId,
                'rejected_at' => now(),
                'rejected_reason' => $reason
            ]);

            AuditLog::log(
                action: 'reject_refund',
                subject: $refund,
                oldValues: ['status' => 'pending_approval'],
                newValues: ['status' => 'rejected', 'rejected_reason' => $reason],
                reason: $reason
            );
        });

        return $refund->fresh();
    }


    public function complete($refundId, $completerId, $reference, $notes = null)
    {
        $refund =  Refund::findOrFail($refundId);

        if (!in_array($refund->status, ['approved', 'processed'])) {
            throw ValidationException::withMessages([
                'refund' => "Hanya refund approved/processed yang bisa diselesaikan. Status: {$refund->status}",
            ]);
        }

        DB::transaction(function () use ($refund, $completerId, $reference, $notes) {
            $refund->update([
                'status' => 'completed',
                'completed_by' => $completerId,
                'completed_at' => now(),
                'completion_reference' => $reference,
                'completion_notes' => $notes
            ]);

            AuditLog::log(
                action: 'completed_refund',
                subject: $refund,
                oldValues: ['status' => $refund->getOriginal('status')],
                newValues: [
                    'status' => 'completed',
                    'reference' => $reference
                ],
                reason: $notes ?? "Referensi pengembalian: {$reference}"
            );
        });

        return $refund->fresh();
    }


    private function generateRefundNumber()
    {
        $prefix = 'RFD-' . now()->format('Ymd');

        $lastRefund = Refund::where('refund_number', 'like', "{$prefix}-%")
            ->orderByDesc('refund_number')
            ->first();

        $sequence = 1;
        if ($lastRefund) {
            $lastSeq = (int) substr($lastRefund->refund_number, -4);
            $sequence = $lastSeq + 1;
        }

        return $prefix . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}
