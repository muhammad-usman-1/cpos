<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The short product form: barcode / code, name, quantity, cost and sale price, plus a low stock level and notes.
 */
class ProductRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['barcode' => trim((string) $this->input('barcode')) ?: null]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Product|null $product */
        $product = $this->route('product');
        $singleVariant = $this->singleVariantId();
        $hasColours = $product && $singleVariant === null;

        return [
            'barcode' => [
                'nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9._\-\/]+$/',
                Rule::unique('product_variants', 'sku')->ignore($singleVariant),
                Rule::unique('barcode_labels', 'code'),
            ],
            'name' => ['required', 'string', 'max:150'],
            'quantity' => [$hasColours ? 'nullable' : 'required', 'numeric', 'min:0', 'max:999999'],
            'cost_price' => [$this->user()->can('cost.view') && ! $product ? 'required' : 'nullable', 'numeric', 'min:0', 'max:9999999'],
            'sale_price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'min_stock' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'quantities' => ['nullable', 'array'],
            'quantities.*' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Product|null $product */
                $product = $this->route('product');
                $quantity = $this->input('quantity');
                $allowsDecimals = $product?->allowsFractionalQuantity() ?? false;

                if (is_numeric($quantity) && ! $allowsDecimals && floor((float) $quantity) != (float) $quantity) {
                    $validator->errors()->add('quantity', __('Whole numbers only for this product.'));
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'barcode.unique' => __('This barcode / code is already used by another product.'),
            'barcode.regex' => __('Use only letters, numbers, dashes, dots and slashes in the barcode / code.'),
        ];
    }

    public function attributes(): array
    {
        return [
            'barcode' => __('validation.attributes.barcode'),
        ];
    }

    /**
     * The variant this form edits: the only one of the product, or null for a new product or one with several colours.
     */
    public function singleVariantId(): ?int
    {
        /** @var Product|null $product */
        $product = $this->route('product');

        if (! $product) {
            return null;
        }

        $ids = $product->variants()->withTrashed()->pluck('id');

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }
}
