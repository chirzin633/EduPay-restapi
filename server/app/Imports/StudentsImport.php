<?php

namespace App\Imports;

use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\StudentClass;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Throwable;

class StudentsImport implements ToCollection, WithHeadingRow, WithValidation
{
    private int $successCount = 0;
    private int $failedCount = 0;
    private array $errors = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            try {
                DB::transaction(function () use ($row, $rowNumber) {

                    $query = Student::query();

                    $query->where('student_id_internal', $row['id_siswa']);

                    if (!empty($row['nis'])) {
                        $query->orWhere('nis', $row['nis']);
                    }

                    if (!empty($row['nisn'])) {
                        $query->orWhere('nisn', $row['nisn']);
                    }

                    $existing = $query->first();

                    if ($existing) {
                        throw new Exception("Duplikasi: ID/NIS/NISN sudah terdaftar");
                    }

                    $student = Student::create([
                        'student_id_internal' => $row['id_siswa'],
                        'nis' => $row['nis'] ?? null,
                        'nisn' => $row['nisn'] ?? null,
                        'full_name' => $row['nama_lengkap'] ?? null,
                        'gender' => strtoupper(substr($row['jenis_kelamin'] ?? 'L', 0, 1)),
                        'birth_date' => $row['tanggal_lahir'] ?? null,
                        'parent_name' => $row['nama_orang_tua'] ?? null,
                        'parent_phone' => $row['no_hp_orang_tua'] ?? null,
                        'status' => 'active'
                    ]);

                    if (!empty($row['tahun_ajaran']) && !empty($row['kelas'])) {
                        $academicYear = AcademicYear::where('name', $row['tahun_ajaran'])->first();

                        if (!$academicYear) {
                            throw new Exception("Tahun ajaran '{$row['tahun_ajaran']}' tidak ditemukan");
                        }

                        StudentClass::create([
                            'student_id' => $student->id,
                            'academic_year_id' => $academicYear->id,
                            'class_name' => $row['kelas'],
                            'grade_level' => $row['tingkat'] ?? null,
                            'enrollment_date' => now()->toDateString(),
                            'is_current' => true
                        ]);
                    }
                });

                $this->successCount++;
            } catch (Throwable $e) {
                $this->failedCount++;
                $this->errors[] = [
                    'row' => $rowNumber,
                    'message' => $e->getMessage(),
                    'data' => $row->toArray()
                ];
            }
        }
    }

    public function rules(): array
    {
        return [
            'id_siswa' => ['required', 'string', 'max:50'],
            'nama_lengkap' => ['required', 'string', 'max:150'],
            'jenis_kelamin' => ['nullable', 'string', 'in:L,P,Laki-laki,Perempuan'],
            'nis' => ['nullable', 'string', 'max:30'],
            'nisn' => ['nullable', 'string', 'max:30'],
            'tanggal_lahir' => ['nullable', 'date'],
            'nama_orang_tua' => ['nullable', 'string', 'max:150'],
            'no_hp_orang_tua' => ['nullable', 'string', 'max:20'],
            'tahun_ajaran' => ['nullable', 'string', 'max:20'],
            'kelas' => ['nullable', 'string', 'max:50'],
            'tingkat' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'id_siswa.required' => 'ID Siswa wajib diisi',
            'nama_lengkap.required' => 'Nama wajib diisi',
            'tanggal_lahir.date' => 'Format tanggal lahir tidak valid (YYYY-MM-DD)'
        ];
    }

    public function getSuccessCount()
    {
        return $this->successCount;
    }

    public function getFailedCount()
    {
        return $this->failedCount;
    }

    public function getErrors()
    {
        return $this->errors;
    }
}
