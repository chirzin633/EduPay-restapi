<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'exists: students,id'],
            'payment_method' => ['required', 'in:cash,bank_transfer,qris'],
            'total_amount' => ['required', 'numeric', 'min:1'],
            'reference_number' => 'nullable|string|max:100',
            'notes' => ['nullable', 'string', 'max:1000'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.bill_id' => ['required', 'exists:bills,id'],
            'allocations.*.allocated_amount' => ['required', 'numeric', 'min:1'],
        ];
    }
}
