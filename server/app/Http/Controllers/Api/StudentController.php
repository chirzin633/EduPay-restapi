<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\StudentService;
use Illuminate\Http\Request;
use Nette\Schema\Elements\TupleType;

class StudentController extends Controller
{
    public function __construct(private StudentService $studentService) {}

    public function index(Request $request)
    {
        $query = Student::with('currentClass.academicYear');

        if ($request->filled('search')) {
            $search = $request->input('search');

            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'ilike', "%{$search}%")
                    ->orWhere('nis', 'ilike', "%{$search}%")
                    ->orWhere('nisn', 'ilike', "%{$search}%")
                    ->orWhere('student_id_internal', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('academic_year_id')) {
            $query->whereHas('currentClass', function ($q) use ($request) {
                $q->where('academic_year_id', $request->input('academic_year_id'));
            });
        }

        $students = $query->orderBy('full_name')->paginate($request->input('per_page', 20));

        return response()->json($students);
    }

    public function show(Student $student)
    {
        $student->load(['currentClass.academicYear', 'studentClasses.academicYear']);

        return response()->json(['data' => $student]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id_internal' => ['required', 'string', 'max:50'],
            'nis' => ['nullable', 'string', 'max:30'],
            'nisn' => ['nullable', 'string', 'max:30'],
            'full_name' => ['required', 'string', 'max:50'],
            'gender' => ['required', 'in:L,P'],
            'birth_date' => ['nullable', 'date'],
            'parent_name' => ['nullable', 'string', 'max:150'],
            'parent_phone' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'in:active, inactive, graduated, transferred, dropped_out'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'academic_year_id' => ['nullable', 'exists:academic_years,id'],
            'class_name' => ['nullable', 'string', 'max:50'],
            'grade_level' => ['nullable', 'string', 'max:50'],
        ]);

        $student = $this->studentService->create($validated);

        return response()->json([
            'message' => 'Siswa berhasil ditambahkan',
            'data' => $student,
        ], 201);
    }

    public function update(Request $request, Student $student)
    {
        $validated = $request->validate([
            'student_id_internal' => ['required', 'string', 'max:50'],
            'nis' => ['nullable', 'string', 'max:30'],
            'nisn' => ['nullable', 'string', 'max:30'],
            'full_name' => ['required', 'string', 'max:50'],
            'gender' => ['required', 'in:L,P'],
            'birth_date' => ['nullable', 'date'],
            'parent_name' => ['nullable', 'string', 'max:150'],
            'parent_phone' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'in:active, inactive, graduated, transferred, dropped_out'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $student = $this->studentService->update($student, $validated);

        return response()->json([
            'message' => 'Data siswa berhasil diperbahuri',
            'data' => $student
        ]);
    }

    public function changeStatus(Request $request, Student $student)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:active, inactive, graduated, transferred, dropped_out'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $student = $this->studentService->changeStatus(
            $student,
            $validated['status'],
            $validated['reason']
        );

        return response()->json([
            'message' => "Status siswa berhasil diubah menjadi {$validated['status']}",
            'data' => $student
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120']
        ]);

        $result = $this->studentService->importFromExcel($request->file('file'));

        $statusCode = $result['failed_count'] > 0 ? 422 : 200;

        return response()->json([
            'message' => "Import selesai: {$result['success_count']} berhasil, {$result['failed_count']} gagal",
            'data' => $result
        ], $statusCode);
    }
}
