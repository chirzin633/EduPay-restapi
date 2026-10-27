<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBillRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bill_type_id' => ['required', 'exists: bill_types, id'],
            'academic_year_id' => ['required', 'exists: academic_years,id'],
            'semester_id' => ['nullable', 'exists: semester,id'],
            'period_label' => ['required', 'string', 'max:30'],
            'due_date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.student_id' => ['required', 'exists: students, id'],
            'items.*.original_amount' => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_reason' => ['required_with:items.*.discount_amount', 'string', 'max:500']
        ];
    }
}
