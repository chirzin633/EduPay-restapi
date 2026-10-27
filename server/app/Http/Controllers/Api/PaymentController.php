<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\VerifyPaymentRequest;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $paymentService) {}

    public function store(StorePaymentRequest $request)
    {
        $params = $request->validated();
        $params['recorded_by'] = Auth::id();

        $payment = $this->paymentService->recordPayment($params);

        return response()->json([
            'message' => 'Pembayaran berhasil dicatat.',
            'data' => $payment
        ], 201);
    }

    public function verify(VerifyPaymentRequest $request, int $paymentId)
    {
        $payment = $this->paymentService->verifyPayment(
            paymentId: $paymentId,
            verifierId: Auth::id(),
            notes: $request->validated('notes')
        );

        return response()->json([
            'message' => 'Pembayaran berhasil diverifikasi',
            'data' => $payment
        ]);
    }

    public function reject(Request $request, int $paymentId)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000']
        ]);

        $payment = $this->paymentService->rejectPayment(
            paymentId: $paymentId,
            verifierId: Auth::id(),
            reason: $validated['reason']
        );

        return response()->json([
            'message' => 'Verifikasi pembayaran ditolak.',
            'data' => $payment
        ]);
    }
}
