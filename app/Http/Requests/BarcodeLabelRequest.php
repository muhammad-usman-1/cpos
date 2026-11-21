<?php

namespace App\Http\Requests;

use App\Http\Controllers\LabelController;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * New price labels: the product, the price printed on them, how many, and the label size.
 */
class BarcodeLabelRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')->whereNull('deleted_at')],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'copies' => ['required', 'integer', 'min:1', 'max:1000'],
            'size' => ['required', Rule::in(array_keys(LabelController::SIZES))],
            'layout' => ['required', Rule::in(['roll', 'sheet'])],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_variant_id.required' => __('Search and choose a product first.'),
        ];
    }

    public function attributes(): array
    {
        return [
            'copies' => __('number of labels'),
        ];
    }
}
