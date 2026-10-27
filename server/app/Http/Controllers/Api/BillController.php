<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApplyDiscountRequest;
use App\Http\Requests\StoreBillRequest;
use App\Services\BillService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BillController extends Controller
{
    public function __construct(
        private BillService $billService
    ) {}

    public function preview(Request $request)
    {
        $validated = $request->validate([
            'bill_type_id' => ['required', 'exists: bill_types, id'],
            'academic_year_id' => ['required', 'exists: academic_years, id'],
            'semester_id' => ['nullable', 'exists: semesters, id'],
            'period_label' => ['required', 'string', 'max: 30'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['exists: students, id']
        ]);

        $result = $this->billService->generatePreview($validated);

        return response()->json([
            'message' => 'Preview generated successfully.',
            'data' => $result
        ]);
    }

    public function store(StoreBillRequest $request)
    {
        $params = $request->validated();
        $params['created_by'] = Auth::id();

        $result = $this->billService->publishBill($params);

        return response()->json([
            'message' => "Berhasil menerbitkan {$result['published']} tagihan." . ($result['skipped_duplicates'] > 0 ? "{$result['skipped_duplicates']} tagihan dilewati karena duplikasi." : ''),
            'data' => $result
        ], 201);
    }

    //  Terapkan diskon pada tagihan yang sudah diterbitkan
    public function applyDiscount(ApplyDiscountRequest $request, int $billId)
    {
        $bill = $this->billService->applyDiscount(
            billId: $billId,
            discountAmount: $request->validated('discount_amount'),
            reason: $request->validated('reason'),
            userId: Auth::id()
        );

        return response()->json([
            'message' => 'Diskon berhasil diterapkan',
            'data' => $bill
        ]);
    }
}
