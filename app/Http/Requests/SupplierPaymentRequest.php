<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierPaymentRequest extends FormRequest
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
            'reference_no' => [Rule::requiredIf(fn () => in_array($this->input('method'), ['cheque', 'bank'], true)), 'nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'reference_no.required' => __('Enter the cheque or transaction number.'),
        ];
    }
}
