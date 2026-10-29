<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApprovedRefundRequest;
use App\Http\Requests\CompletedRefundRequest;
use App\Http\Requests\RejectRefundRequest;
use App\Http\Requests\StoreRefundRequest;
use App\Services\RefundService;
use Illuminate\Support\Facades\Auth;

use function Laravel\Prompts\note;

class RefundController extends Controller
{
    public function __construct(private RefundService $refundService) {}

    public function store(StoreRefundRequest $request)
    {
        $params = $request->validated();
        $params['requested_by'] = Auth::id();

        $refund = $this->refundService->createDraft($params);

        return response()->json([
            'message' => 'Draft refund berhasil dibuat',
            'data' => $refund
        ], 201);
    }

    public function submit($refundId)
    {
        $refund = $this->refundService->submitForApproval($refundId, Auth::id());

        return response()->json([
            'message' => 'Refund berhasil diajukan untuk persetujuan',
            'data' => $refund
        ]);
    }

    public function approve(ApprovedRefundRequest $request, $refundId)
    {
        $refund = $this->refundService->approve(
            refundId: $refundId,
            approverId: Auth::id(),
            notes: $request->input('notes')
        );

        return response()->json([
            'message' => 'Refund berhasil disetujui',
            'data' => $refund
        ]);
    }

    public function reject(RejectRefundRequest $request, $refundId)
    {
        $refund = $this->refundService->reject(
            refundId: $refundId,
            rejectorId: Auth::id(),
            reason: $request->input('reason')
        );

        return response()->json([
            'message' => 'Refund ditolak',
            'data' => $refund
        ]);
    }

    public function complete(CompletedRefundRequest $request, $refundId)
    {
        $refund = $this->refundService->complete(
            refundId: $refundId,
            completerId: Auth::id(),
            reference: $request->input('completion_reference'),
            notes: $request->input('notes')
        );

        return response()->json([
            'message' => 'Refund ditandai sebagai selesai',
            'data' => $refund
        ]);
    }
}
