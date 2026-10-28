<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectRefundRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000']
        ];
    }
}
