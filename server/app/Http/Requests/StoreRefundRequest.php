<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRefundRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            'payment_id' => ['required', 'exists: payments, id'],
            'refund_amount' => ['required', 'numeric', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
            'refund_method' => ['nullable', 'in:cash, bank_transfer,qris'],
            'recipient_account_name' => ['nullable', 'string', 'max:150'],
            'recipient_account_number' => ['nullable', 'string', 'max:100']
        ];
    }
}
