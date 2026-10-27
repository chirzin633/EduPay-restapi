<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplyDiscountRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            'discount_amount' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:500']
        ];
    }
}
