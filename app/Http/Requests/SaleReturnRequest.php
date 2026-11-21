<?php

namespace App\Http\Requests;

use App\Enums\RefundMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaleReturnRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'return_date' => ['required', 'date', 'before_or_equal:today'],
            'refund_method' => ['required', Rule::enum(RefundMethod::class)],
            'notes' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array'],
            'items.*.sale_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
