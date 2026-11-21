<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\ProductVariant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PurchaseRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'purchase_date' => ['required', 'date', 'before_or_equal:today'],
            'invoice_no' => ['nullable', 'string', 'max:60'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'other_charges' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => [Rule::requiredIf(fn (): bool => (float) $this->input('paid_amount') > 0), 'nullable', Rule::enum(PaymentMethod::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'items.*.rate' => ['required', 'numeric', 'min:0', 'max:9999999'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $items = (array) $this->input('items', []);
                $variants = ProductVariant::query()
                    ->with('product')
                    ->whereIn('id', array_filter(array_column($items, 'product_variant_id'), 'is_numeric'))
                    ->get()
                    ->keyBy('id');

                foreach ($items as $index => $item) {
                    $variant = $variants->get($item['product_variant_id'] ?? null);
                    $quantity = $item['quantity'] ?? null;

                    if ($variant && is_numeric($quantity) && ! $variant->product->allowsFractionalQuantity() && floor((float) $quantity) != (float) $quantity) {
                        $validator->errors()->add("items.$index.quantity", __('Whole numbers only for items sold by :label.', ['label' => $variant->product->unit->label()]));
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function purchaseData(): array
    {
        return $this->safe()->except('attachment');
    }

    public function attributes(): array
    {
        return [
            'items.*.product_variant_id' => __('validation.attributes.product'),
            'items.*.quantity' => __('validation.attributes.quantity'),
            'items.*.rate' => __('validation.attributes.rate'),
        ];
    }
}
