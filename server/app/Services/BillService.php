<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\BillType;
use App\Models\Student;
use App\Services\Concern\ChecksPeriodClosure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillService
{
    use ChecksPeriodClosure;

    // Generate preview tagihan sebelum penerbitan massal.
    // Tidak menyimpan ke database, hanya mengembalikan array untuk ditampilkan di UI.
    public function generatePreview($params)
    {
        $billType = BillType::findOrFail($params['bill_type_id']);

        $students = isset($params['student_ids'])
            ? Student::whereIn('id', $params['student_ids'])->active()->get()
            : Student::active()->get();

        $items = [];

        foreach ($students as $student) {
            // Ambil kelas aktif siswa untuk lookup tarif
            $currentClass = $student->currentClass;
            $gradeLevel = $currentClass?->grade_level;
            $className = $currentClass?->class_name;

            // Hitung nominal berdasarkan prioritas tarif
            $originalAmount = $billType->getAmountForStudent($gradeLevel, $className);

            $items[] = [
                'student_id' => $student->id,
                'student_name' => $student->full_name,
                'class_name' => $className,
                'grade_level' => $gradeLevel,
                'original_amount' => $originalAmount,
                'discount_amount' => '0.00',
                'final_amount' => $originalAmount,
            ];
        }

        $totalAmount = collect($items)->sum(fn($item) => (float) $item['final_amount']);

        return [
            'items' => $items,
            'total_count' => count($items),
            'total_amount' => number_format($totalAmount, 2, '.', ''),
        ];
    }

    public function publishBill($params)
    {
        $this->ensurePeriodIsOpen(
            $params['academic_year_id'],
            $params['semester_id'] ?? null,
            $params['period_label']
        );

        $billType = BillType::findOrFail($params['bill_type_id']);
        $published = 0;
        $skippedDuplicates = 0;

        DB::transaction(function () use ($params, $billType, &$published, &$skippedDuplicates) {
            foreach ($params['items'] as $item) {
                $student = Student::findOrFail($item['student_id']);

                $exists = Bill::where('student_id', $student->id)
                    ->where('bill_type_id', $billType->id)
                    ->where('period_label', $params['period_label'])
                    ->where('academic_year_id', $params['academic_year_id'])
                    ->exists();

                if ($exists) {
                    $skippedDuplicates++;
                    continue;
                }

                $originalAmount = (float) $item['original_amount'];
                $discountAmount = (float) ($item['discount_amount'] ?? 0);

                if ($discountAmount > $originalAmount) {
                    throw ValidationException::withMessages([
                        'discount' => "Diskon untuk {$student->full_name} tidak boleh melebihi nominal asli ({$originalAmount})"
                    ]);
                }

                $finalAmount = $originalAmount - $discountAmount;

                $currentClass = $student->currentClass;

                $bill = Bill::create([
                    'bill_number' => $this->generateBillNumber(),
                    'student_id' => $student->id,
                    'bill_type_id' => $billType->id,
                    'academic_year_id' => $params['academic_year_id'],
                    'semester_id' => $params['semester_id'] ?? null,
                    'student_name_snapshot' => $student->full_name,
                    'class_name_snapshot' => $currentClass?->class_name,
                    'grade_level_snapshot' => $currentClass?->grade_level,
                    'period_label' => $params['period_label'],
                    'due_date' => $params['due_date'],
                    'original_amount' => $originalAmount,
                    'discount_amount' => $discountAmount,
                    'final_amount' => $finalAmount,
                    'paid_amount' => 0,
                    'discount_reason' => $item['discount_reason'] ?? null,
                    'discount_by' => ($discountAmount > 0) ? $params['created_by'] : null,
                    'status' => 'unpaid',
                    'published_at' => now(),
                    'created_by' => $params['created_by']
                ]);

                AuditLog::log(
                    action: 'create_bill',
                    subject: $bill,
                    newValues: [
                        'bill_number' => $bill->bill_number,
                        'student' => $student->full_name,
                        'final_amount' => $finalAmount,
                        'period' => $params['period_label']
                    ],
                    reason: $discountAmount > 0 ? "Diskon: {$item['discount_reason']}" : null
                );

                $published++;
            }
        });

        return [
            'published' => $published,
            'skipped_duplicates' => $skippedDuplicates
        ];
    }


    public function applyDiscount($billId, $discountAmount, $reason, $userId)
    {
        $bill = Bill::findOrFail($billId);

        if (!in_array($bill->status, ['unpaid', 'partial'])) {
            throw ValidationException::withMessages([
                'bill' => "Diskon hanya bisa diterapkan pada tagihan berstatus unpaid atau partial. Status saat ini: {$bill->status}"
            ]);
        }

        $this->ensurePeriodIsOpen(
            $bill->academic_year_id,
            $bill->semester_id,
            $bill->period_label
        );

        if ($discountAmount > $bill->original_amount) {
            throw ValidationException::withMessages([
                'discount_amount' => "Diskon tidak boleh melebihi nominal asli ({$bill->original_amount})."
            ]);
        }

        $oldValues = [
            'discount_amount' => $bill->discount_amount,
            'final_amount' => $bill->final_amount,
            'discount_reason' => $bill->discount_reason
        ];

        $newFinalAmount = $bill->original_amount - $discountAmount;

        DB::transaction(function () use ($bill, $discountAmount, $reason, $userId, $newFinalAmount, $oldValues) {
            $bill->update([
                'discount_amount' => $discountAmount,
                'final_amount' => $newFinalAmount,
                'discount_reason' => $reason,
                'discount_by' => $userId
            ]);

            AuditLog::log(
                action: 'discount',
                subject: $bill,
                oldValues: $oldValues,
                newValues: [
                    'discount_amount' => $discountAmount,
                    'final_amount' => $newFinalAmount,
                    'discount_reason' => $reason
                ],

                reason: $reason
            );
        });

        return $bill->fresh();
    }

    private function generateBillNumber()
    {
        $prefix = 'BILL-' . now()->format('Ymd');

        $lastBill = Bill::where('bill_number', 'like', "{$prefix}-%")
            ->orderByDesc('bill_number')
            ->first();

        $sequence = 1;
        if ($lastBill) {
            $lastSeq = (int) substr($lastBill->bill_number, -4);
            $sequence = $lastSeq + 1;
        }

        return $prefix . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}
