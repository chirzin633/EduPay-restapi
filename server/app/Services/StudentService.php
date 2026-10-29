<?php

namespace App\Services;

use App\Imports\StudentsImport;
use App\Models\AuditLog;
use App\Models\Student;
use App\Models\StudentClass;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException as ValidatorsValidationException;

class StudentService
{
    public function create($data)
    {
        $this->validateUniqueness($data);

        $student = DB::transaction(function () use ($data) {
            $student = Student::create([
                'student_id_internal' => $data['student_id_internal'],
                'nis' => $data['nis'] ?? null,
                'nisn' => $data['nisn'] ?? null,
                'full_name' => $data['full_name'],
                'gender' => $data['gender'],
                'birth_date' => $data['birth_date'] ?? null,
                'parent_name' => $data['parent_name'] ?? null,
                'parent_phone' => $data['parent_phone'] ?? null,
                'status' => $data['status'] ?? 'active',
                'notes' => $data['notes'] ?? null,
            ]);

            if (!empty($data['academic_year_id']) && !empty($data['class_name'])) {
                $this->assignClass($student, $data);
            }

            AuditLog::log(
                action: 'create_student',
                subject: $student,
                newValues: $student->toArray()
            );

            return $student;
        });

        return $student->fresh()->load('currentClass');
    }

    public function update(Student $student, $data)
    {
        $this->validateUniqueness($data, $student->id);

        $oldValues = $student->getOriginal();

        DB::transaction(function () use ($student, $data, $oldValues) {
            $student->update([
                'student_id_internal' => $data['student_id_internal'],
                'nis' => $data['nis'] ?? null,
                'nisn' => $data['nisn'] ?? null,
                'full_name' => $data['full_name'],
                'gender' => $data['gender'],
                'birth_date' => $data['birth_date'] ?? null,
                'parent_name' => $data['parent_name'] ?? null,
                'parent_phone' => $data['parent_phone'] ?? null,
                'status' => $data['status'] ?? $student->status,
                'notes' => $data['notes'] ?? null,
            ]);

            AuditLog::log(
                action: 'update_student',
                subject: $student,
                oldValues: $oldValues,
                newValues: $student->getChanges()
            );
        });
    }

    public function changeStatus($student, $newStatus, $reason)
    {
        if (!in_array($newStatus, ['active', 'inactive', 'graduated', 'transferred', 'dropped_out'])) {
            throw ValidationException::withMessages([
                'status' => "Status tidak valid: {$newStatus}"
            ]);
        }

        $oldStatus = $student->status;

        DB::transaction(function () use ($student, $newStatus, $reason, $oldStatus) {
            $student->update(['status' => $newStatus]);

            // Nonaktifkan kelas aktif jika siswa tidak lagi active
            if ($newStatus !== 'active') {
                StudentClass::where('student_id', $student->id)
                    ->where('is_current', true)
                    ->update([
                        'is_current' => false,
                        'exit_date' => now()->toDateString()
                    ]);
            }

            AuditLog::log(
                action: 'change_student_status',
                subject: $student,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => $newStatus],
                reason: $reason
            );
        });

        return $student->fresh();
    }

    public function importFromExcel($file)
    {
        $import = new StudentsImport();

        try {
            Excel::import($import, $file);
        } catch (ValidatorsValidationException $e) {
            $failures = $e->failures();

            $errors = [];

            foreach ($failures as $failure) {
                $errors[] = [
                    'row' => $failure->row(),
                    'column' => $failure->attribute(),
                    'errors' => $failure->errors(),
                    'values' => $failure->values()
                ];
            }
            return [
                'success_count' => $import->getSuccessCount(),
                'failed_count' => $import->getFailedCount(),
                'errors' => $import->getErrors()
            ];
        }
    }

    private function validateUniqueness($data, $excludeId = null)
    {
        $query = Student::query();

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if (!empty($data['student_id_internal'])) {
            if ((clone $query)->where('student_id_internal', $data['student_id_internal'])->exists()) {
                throw ValidationException::withMessages([
                    'student_id_internal' => "ID Siswa '{$data['student_id_internal']}' sudah terdfdaftar."
                ]);
            }
        }

        if (!empty($data['nis'])) {
            if ((clone $query)->where('nis', $data['nis'])->exists()) {
                throw ValidationException::withMessages([
                    'nis' => "NIS '{$data['nis']}' sudah digunakan oleh siswa lain."
                ]);
            }
        }

        if (!empty($data['nisn'])) {
            if ((clone $query)->where('nisn', $data['nisn'])->exists()) {
                throw ValidationException::withMessages([
                    'nisn' => "NISN '{$data['nisn']}' sudah digunakan oleh siswa lain."
                ]);
            }
        }
    }

    private function assignClass(Student $student, $data)
    {
        StudentClass::where('student_id', $student->id)
            ->where('is_current', true)
            ->update([
                'is_current' => false,
                'exit_date' => now()->toDateString()
            ]);

        StudentClass::create([
            'student_id' => $student->id,
            'academic_year_id' => $data['academic_year_id'],
            'class_name' => $data['class_name'],
            'grade_level' => $data['grade_level'] ?? null,
            'enrollment_date' => $data['enrollment_date'] ?? now()->toDateString(),
            'is_current' => true
        ]);
    }
}
