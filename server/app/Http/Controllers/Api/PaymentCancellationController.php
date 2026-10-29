<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentCancelationsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentCancellationController extends Controller
{
    public function __construct(private PaymentCancelationsService $cancellationService) {}

    public function store(Request $request, $paymentId)
    {
        $validated =  $request->validate([
            'reason' => ['required', 'string', 'max:1000']
        ]);

        $cancellation = $this->cancellationService->request(
            paymentId: $paymentId,
            requesterId: Auth::id(),
            reason: $validated['reason']
        );

        return response()->json([
            'message' => "Permohonan pembatalan berhasil diajukan.",
            'data' => $cancellation
        ]);
    }

    public function approve(Request $request, $cancellationId)
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000']
        ]);

        $cancellation = $this->cancellationService->approve(
            cancellationId: $cancellationId,
            reviewerId: Auth::id(),
            notes: $validated['notes'] ?? null
        );

        return response()->json([
            'message' => "Pembatalan disetujui. Saldo tagihan telah dipulihkan.",
            'data' => $cancellation
        ]);
    }

    public function reject(Request $request, $cancellationId)
    {
        $validated =  $request->validate([
            'reason' => ['required', 'string', 'max:1000']
        ]);

        $cancellation = $this->cancellationService->reject(
            cancellationId: $cancellationId,
            reviewerId: Auth::id(),
            reason: $validated['reason']
        );

        return response()->json([
            'message' => 'Permohonan pembatalan ditolak.',
            'data' => $cancellation
        ]);
    }
}
