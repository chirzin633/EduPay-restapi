<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompletedRefundRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            'completion_reference' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000']
        ];
    }
}
