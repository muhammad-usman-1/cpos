<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceivePaymentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference_no' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:255'],
            'allocations' => ['nullable', 'array'],
            'allocations.*.type' => ['required', Rule::in(['sale', 'stitching_order'])],
            'allocations.*.id' => ['required', 'integer'],
            'allocations.*.amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
