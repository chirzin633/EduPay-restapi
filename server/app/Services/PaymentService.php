<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Student;
use App\Services\Concern\ChecksPeriodClosure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    use ChecksPeriodClosure;

    public function recordPayment($params)
    {
        $paymentDate = $params['payment_date'];
        $student = Student::findOrFail($params['student_id']);

        $initialStatus = match ($params['payment_method']) {
            'cash' => 'verified',
            'bank_transfer', 'qris' => 'pending_verification',
            default => 'pending_verification'
        };

        $payment = null;

        $payment =  DB::transaction(function () use ($params, $student, $initialStatus, &$payment) {
            $payment = Payment::create([
                'payment_number' => $this->generatePaymentNumber(),
                'student_id' => $student->id,
                'recorded_by' => $params['recorded_by'],
                'payment_method' => $params['payment_method'],
                'total_amount' => $params['total_amount'],
                'payment_date' => $params['payment_date'],
                'reference_number' => $params['reference_number'] ?? null,
                'notes' => $params['notes'] ?? null,
                'attachment_path' => $params['attachment_path'] ?? null,
                'status' => $initialStatus
            ]);

            $totalAllocated = 0;

            foreach ($params['allocations'] as $allocation) {
                $bill = Bill::lockForUpdate()->findOrFail($allocation['bill_id']);

                if ($bill->student_id !== $student->id) {
                    throw ValidationException::withMessages([
                        'allocations' => "Tagihan {$bill->bill_number} bukan milik siswa {$student->full_name}"
                    ]);
                }

                $this->ensurePeriodIsOpen(
                    $bill->academic_year_id,
                    $bill->semester_id,
                    $bill->period_label
                );

                $remaining = (float) $bill->final_amount - (float) $bill->paid_amount;
                $allocatedAmount = (float) $allocation['allocated_amount'];

                if ($allocatedAmount <= 0) {
                    throw ValidationException::withMessages([
                        'allocations' => "Nominal alokasi harus lebih besar dari 0.",
                    ]);
                }

                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'bill_id' => $bill->id,
                    'allocated_amount' => $allocatedAmount
                ]);

                $newPaidAmount = (float) $bill->paid_amount + $allocatedAmount;
                $newStatus = match (true) {
                    $newPaidAmount >= (float) $bill->final_amount => 'paid',
                    $newPaidAmount > 0 => 'partial',
                    default => $bill->status
                };

                $bill->update([
                    'paid_amount' => $newPaidAmount,
                    'status' => $newStatus
                ]);

                $totalAllocated += $allocatedAmount;
            }

            if ($totalAllocated > (float) $params['total_amount']) {
                throw ValidationException::withMessages([
                    'total_amount' => "Total alokasi (Rp" . number_format($totalAllocated, 0, ',', '.')
                        . ") melebihi nominal pembayaran (Rp" . number_format((float)$params['total_amount'], 0, ',', '.') . ").",
                ]);
            }

            AuditLog::log(
                action: 'create_payment',
                subject: $payment,
                newValues: [
                    'payment_number' => $payment->payment_number,
                    'student' => $student->full_name,
                    'total_amount' => $params['total_amount'],
                    'status' => $initialStatus,
                    'allocations' => $params['allocations'],
                ],
                reason: $params['notes'] ?? null
            );

            return $payment;
        });

        return $payment->fresh()->load('allocations.bill');
    }

    public function verifyPayment($paymentId, $verifierId, $notes = null)
    {
        $payment = Payment::findOrFail($paymentId);

        if ($payment->status !== 'pending_verification') {
            throw ValidationException::withMessages([
                'payment' => "Pembayaran ini tidak dalam status menunggu verifikasi. Status saat ini: {$payment->status}"
            ]);
        }

        if ($payment->recorded_by === $verifierId) {
            throw ValidationException::withMessages([
                'verifier' => "Anda tidak dapat memverifikasi pembayaran yang Anda catat sendiri. Minta rekan lain untuk memverifikasi."
            ]);
        }

        $oldStatus = $payment->status;

        DB::transaction(function () use ($payment, $verifierId, $notes, $oldStatus) {
            $payment->update([
                'status' => 'verified',
                'verified_by' => $verifierId,
                'verified_at' => now(),
                'verification_notes' => $notes
            ]);

            AuditLog::log(
                action: 'verify_payment',
                subject: $payment,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => 'verified', 'verified_by' => $verifierId],
                reason: $notes
            );
        });

        return $payment->fresh();
    }

    public function rejectPayment($paymentId, $verifierId, $reason)
    {
        $payment = Payment::findOrFail($paymentId);

        if ($payment->status !== 'pending_verification') {
            throw ValidationException::withMessages([
                'payment' => "Hanya pembayaran pending_verification yang bisa ditolak."
            ]);
        }

        DB::transaction(function () use ($payment, $verifierId, $reason) {
            $payment->update([
                'status' => 'rejected',
                'rejected_by' => $verifierId,
                'rejected_at' => now(),
                'rejection_reason' => $reason
            ]);

            AuditLog::log(
                action: 'reject_payment',
                subject: $payment,
                oldValues: ['status' => 'pending_verification'],
                newValues: ['status' => 'rejected', 'reason' => $reason]
            );
        });
        return $payment->fresh();
    }

    private function generatePaymentNumber()
    {
        $prefix = 'PAY-' . now()->format('Ymd');

        $lastPayment = Payment::where('payment_number', 'like', "{$prefix}-%")
            ->orderByDesc('payment_number')
            ->first();

        $sequence = 1;
        if ($lastPayment) {
            $lasSeq = (int) substr($lastPayment->payment_number, -4);
            $sequence = $lasSeq + 1;
        }

        return $prefix . '-' . str_pad($sequence, 4, 0, STR_PAD_LEFT);
    }
}
