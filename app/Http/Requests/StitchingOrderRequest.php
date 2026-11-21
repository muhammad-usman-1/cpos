<?php

namespace App\Http\Requests;

use App\Enums\ClothSource;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Models\StitchingOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StitchingOrderRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var StitchingOrder|null $order */
        $order = $this->route('order');

        return [
            'customer_id' => [$order ? 'prohibited' : 'required', 'integer', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'customer_profile_id' => ['required', 'integer'],
            'type' => ['required', Rule::enum(OrderType::class)],
            'parent_order_id' => ['nullable', 'required_if:type,alteration', 'integer'],
            'cloth_source' => ['required', Rule::enum(ClothSource::class)],
            'sale_id' => ['nullable', 'integer'],
            'cloth_description' => ['nullable', 'required_if:cloth_source,customer', 'string', 'max:255'],
            'cloth_quantity' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'pieces' => ['required', 'integer', 'min:1', 'max:100'],
            'design_notes' => ['nullable', 'string', 'max:2000'],
            'stitching_charges' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'extras' => ['nullable', 'array'],
            'extras.*.description' => ['nullable', 'string', 'max:100'],
            'extras.*.amount' => ['nullable', 'numeric', 'min:0'],
            'promised_date' => ['required', 'date', $order ? 'after_or_equal:'.$order->order_date->toDateString() : 'after_or_equal:today'],
            'measurement_unit' => ['required', Rule::in(['in', 'cm'])],
            'measurements' => ['nullable', 'array'],
            'measurements.*' => ['nullable', 'string', 'max:100'],
            'measurement_notes' => ['nullable', 'string', 'max:2000'],
            'measurement_photo' => ['nullable', 'image', 'max:4096'],
            'advance_amount' => [$order ? 'prohibited' : 'nullable', 'numeric', 'min:0'],
            'advance_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'advance_reference' => ['nullable', 'string', 'max:80'],
        ];
    }

    public function messages(): array
    {
        return [
            'cloth_description.required_if' => __("Describe the customer's cloth (colour, fabric, pieces)."),
            'parent_order_id.required_if' => __('Choose the original order for an alteration.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function orderData(): array
    {
        return $this->safe()->except('measurement_photo');
    }
}
