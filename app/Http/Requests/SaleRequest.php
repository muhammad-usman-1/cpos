<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\ProductVariant;
use App\Rules\UniquePhone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaleRequest extends FormRequest
{
    /**
     * The phone typed in the counter's customer card, stored format.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'new_customer_phone' => Customer::normalizePhone($this->input('new_customer_phone')),
            'new_customer_name' => trim((string) $this->input('new_customer_name')) ?: null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['complete', 'hold'])],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'new_customer_phone' => ['nullable', 'string', 'min:7', 'max:20'],
            'new_customer_name' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:255'],
            'bill_discount' => ['nullable', 'numeric', 'min:0'],
            'extra_charges' => ['nullable', 'numeric', 'min:0'],
            'cash_received' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            // Bills made while the internet was down: their browser ID and when they were really made.
            'client_uuid' => ['nullable', 'uuid'],
            'offline_at' => ['nullable', 'date', 'after:-30 days', 'before:+10 minutes'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')->whereNull('deleted_at')],
            'items.*.barcode_label_id' => ['nullable', 'integer', Rule::exists('barcode_labels', 'id')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999'],
            'items.*.price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'payments' => ['nullable', 'array'],
            'payments.*.method' => ['required_with:payments.*.amount', Rule::enum(PaymentMethod::class)],
            'payments.*.amount' => ['nullable', 'numeric', 'min:0'],
            'payments.*.reference_no' => ['nullable', 'string', 'max:80'],
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
                $variants = ProductVariant::query()->with('product')
                    ->whereIn('id', array_filter(array_column($items, 'product_variant_id'), 'is_numeric'))
                    ->get()->keyBy('id');

                foreach ($items as $index => $item) {
                    $variant = $variants->get($item['product_variant_id'] ?? null);
                    $quantity = $item['quantity'] ?? null;

                    if ($variant && is_numeric($quantity) && ! $variant->product->allowsFractionalQuantity() && floor((float) $quantity) != (float) $quantity) {
                        $validator->errors()->add("items.$index.quantity", __('Whole numbers only for :name.', ['name' => $variant->product->name]));
                    }
                }
            },
            // A phone that is not a customer yet: a name is needed, and the number must not belong to a supplier or staff.
            function (Validator $validator): void {
                $phone = $this->input('new_customer_phone');

                if ($this->filled('customer_id') || ! $phone || $validator->errors()->has('new_customer_phone') || $this->customerForPhone()) {
                    return;
                }

                if (! $this->filled('new_customer_name')) {
                    $validator->errors()->add('new_customer_name', __('Enter the customer\'s name to save this new customer with the bill.'));
                }

                (new UniquePhone('customer'))->validate('new_customer_phone', $phone, function (string $message) use ($validator): void {
                    $validator->errors()->add('new_customer_phone', $message);
                });
            },
        ];
    }

    /**
     * The existing customer for the typed phone: their own number, alternate number, or a family member's number.
     */
    public function customerForPhone(): ?Customer
    {
        $phone = $this->input('new_customer_phone');

        if (! $phone) {
            return null;
        }

        return Customer::query()
            ->where(fn ($query) => $query->where('phone', $phone)
                ->orWhere('alt_phone', $phone)
                ->orWhereHas('profiles', fn ($profiles) => $profiles->where('phone', $phone)))
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function saleData(): array
    {
        return $this->safe()->except(['action', 'new_customer_phone', 'new_customer_name']);
    }

    public function attributes(): array
    {
        return [
            'items.*.product_variant_id' => __('validation.attributes.product'),
            'items.*.quantity' => __('validation.attributes.quantity'),
            'items.*.price' => __('validation.attributes.price'),
            'items.*.discount' => __('validation.attributes.discount'),
            'payments.*.method' => __('validation.attributes.payment method'),
            'payments.*.amount' => __('validation.attributes.payment amount'),
        ];
    }
}
