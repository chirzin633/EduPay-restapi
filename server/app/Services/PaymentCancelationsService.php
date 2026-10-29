<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\PaymentCancellation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentCancelationsService
{
    public function request($paymentId, $requesterId, $reason)
    {
        $payment = Payment::findOrFail($paymentId);

        if ($payment->status !== 'verified') {
            throw ValidationException::withMessages([
                'payment' => "Hanya pembayaran terverifikasi yang bisa dibatalkan. Status saat ini: {$payment->status}"
            ]);
        }

        $existingPending =  PaymentCancellation::where('payment_id', $paymentId)
            ->where('status', 'pending_approval')
            ->exists();

        if ($existingPending) {
            throw ValidationException::withMessages([
                'payment' => "Sudah ada permohonan pembatalan yang menunggu persetujuan untuk pembayaran ini"
            ]);
        }

        $alreadyCancelled = PaymentCancellation::where('payment_id', $paymentId)
            ->where('status', 'approved')
            ->exists();

        if ($alreadyCancelled) {
            throw ValidationException::withMessages([
                "payment" => "Pembayaran ini sudah dibatalkan sebelumnya."
            ]);
        }

        $cancellation = DB::transaction(function ()  use ($payment, $requesterId, $reason) {
            $cancellation = PaymentCancellation::create([
                'payment_id' => $payment->id,
                'requested_by' => $requesterId,
                'reason' => $reason,
                'status' => 'pending_approval'
            ]);

            AuditLog::log(
                action: 'request_payment_cancellation',
                subject: $cancellation,
                newValues: [
                    'payment_number' => $payment->payment_number,
                    'total_amount' => $payment->total_amount,
                    'reason' => $reason
                ]
            );
            return $cancellation;
        });

        return $cancellation->fresh();
    }

    public function approve($cancellationId, $reviewerId, $notes = null)
    {
        $cancellation = PaymentCancellation::with('payment.allocations.bill')->findOrFail($cancellationId);

        if (!$cancellation->isPending()) {
            throw ValidationException::withMessages([
                'cancellation' => "Permohonan pembatalan tidak dalam status pending. Status: {$cancellation->status}"
            ]);
        }

        if ($cancellation->requested_by === $reviewerId) {
            throw ValidationException::withMessages([
                'reviewer' => "Anda tidak dapat menyetujui pembatalan yang Anda ajukan sendiri."
            ]);
        }

        DB::transaction(function () use ($cancellation, $reviewerId, $notes) {
            $payment = $cancellation->payment;
            foreach ($payment->allocations as $allocation) {
                $bill = Bill::lockForUpdate()->find($allocation->bill_id);

                if ($bill) {
                    $newPaidAmount = max(0, (float) $bill->paid_amount - (float) $allocation->allocated_amount);

                    $newStatus = match (true) {
                        $newPaidAmount <= 0 => 'unpaid',
                        $newPaidAmount < (float) $bill->final_amount => 'partial',
                        $newPaidAmount >= (float) $bill->final_amount => 'paid',
                    };

                    $oldValues = [
                        'paid_amount' => $bill->paid_amount,
                        'status' => $bill->status
                    ];

                    $bill->update([
                        'paid_amount' => $newPaidAmount,
                        'status' => $newStatus
                    ]);

                    AuditLog::log(
                        action: 'reverse_allocation',
                        subject: $bill,
                        oldValues: $oldValues,
                        newValues: [
                            'paid_amount' => $newPaidAmount,
                            'status' => $newStatus,
                            'reversed_from_payment' => $payment->payment_number
                        ],
                        reason: "Pembatalan pembayaran {$payment->payment_number}: {$cancellation->reason}"
                    );
                }
            }

            $payment->allocations()->delete();

            $payment->update([
                'status' => 'cancelled',
                'cancelled_by' => $reviewerId,
                'cancelled_at' => now(),
                'cancellation_reason' => $cancellation->reason
            ]);

            $cancellation->update([
                'status' => 'approved',
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
                'review_notes' => $notes
            ]);

            AuditLog::log(
                action: 'approve_payment_cancellation',
                subject: $cancellation,
                oldValues: ['status' => 'pending_approval'],
                newValues: [
                    'status' => 'approved',
                    'reviewed_by' => $reviewerId
                ],
                reason: $notes ?? $cancellation->reason
            );
        });

        return $cancellation->fresh()->load('payment');
    }

    public function reject($cancellationId, $reviewerId, $reason)
    {
        $cancellation = PaymentCancellation::findOrFail($cancellationId);

        if (!$cancellation->isPending()) {
            throw ValidationException::withMessages([
                'cancellation' => "Hanya permohonan pending yang bisa ditolak. Status: {$cancellation->status}",
            ]);
        }

        if ($cancellation->requested_by === $reviewerId) {
            throw ValidationException::withMessages([
                'reviewer' => "Anda tidak dapat menolak pembatalan yang Anda ajukan sendiri.",
            ]);
        }

        DB::transaction(function () use ($cancellation, $reviewerId, $reason) {
            $cancellation->update([
                'status' => 'rejected',
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
                'review_notes' => $reason
            ]);
            AuditLog::log(
                action: 'reject_payment_cancellation',
                subject: $cancellation,
                oldValues: ['status' => 'pending_approval'],
                newValues: ['status' => 'rejected', 'reason' => $reason],
                reason: $reason
            );
        });

        return $cancellation->fresh();
    }
}
