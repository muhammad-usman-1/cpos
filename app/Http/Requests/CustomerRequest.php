<?php

namespace App\Http\Requests;

use App\Enums\CustomerTag;
use App\Enums\FamilyRelation;
use App\Enums\MeasurementTemplate;
use App\Models\Customer;
use App\Rules\UniquePhone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Customer details, and on a new customer the family members added with them (CUS01, CUS04, CUS05).
 */
class CustomerRequest extends FormRequest
{
    /**
     * Phone numbers are compared in their stored format; empty family rows are ignored.
     */
    protected function prepareForValidation(): void
    {
        $family = collect((array) $this->input('family', []))
            ->filter(fn ($row) => is_array($row) && trim(($row['name'] ?? '').($row['phone'] ?? '')) !== '')
            ->map(fn (array $row) => array_merge($row, ['phone' => Customer::normalizePhone($row['phone'] ?? null)]))
            ->values()
            ->all();

        $this->merge([
            'phone' => Customer::normalizePhone($this->input('phone')),
            'alt_phone' => Customer::normalizePhone($this->input('alt_phone')),
            'family' => $family,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Customer|null $customer */
        $customer = $this->route('customer');

        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'min:7', 'max:20', new UniquePhone('customer', $customer?->id)],
            'alt_phone' => ['nullable', 'string', 'min:7', 'max:20', 'different:phone', new UniquePhone('customer', $customer?->id)],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:80'],
            'photo' => ['nullable', 'image', 'max:4096'],
            'remove_photo' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'tag' => ['nullable', Rule::enum(CustomerTag::class)],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'preferred_language' => ['nullable', Rule::in(['en', 'ur'])],
            'birthday' => ['nullable', 'date', 'before:today'],
            'anniversary' => ['nullable', 'date'],
            'is_active' => ['boolean'],
            'first_profile_template' => [$customer ? 'nullable' : 'required', Rule::enum(MeasurementTemplate::class)],
            'family' => [$customer ? 'prohibited' : 'nullable', 'array', 'max:15'],
            'family.*.name' => ['required', 'string', 'max:80'],
            'family.*.relation' => ['required', Rule::enum(FamilyRelation::class), Rule::notIn([FamilyRelation::Self->value])],
            'family.*.phone' => ['nullable', 'string', 'min:7', 'max:20', 'distinct', new UniquePhone],
            'family.*.template' => ['required', Rule::enum(MeasurementTemplate::class)],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ((array) $this->input('family', []) as $index => $row) {
                    $phone = $row['phone'] ?? null;

                    if ($phone && in_array($phone, [$this->input('phone'), $this->input('alt_phone')], true)) {
                        $validator->errors()->add("family.$index.phone", __('This number is already the customer\'s own phone.'));
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'alt_phone.different' => __('The alternate phone must be different from the main phone.'),
            'family.*.phone.distinct' => __('The same phone number is entered twice on this form.'),
            'family.*.name.required' => __('Enter the family member\'s name.'),
            'photo.image' => __('The photo must be a picture (JPG, PNG or WebP).'),
            'photo.max' => __('The photo must not be larger than 4 MB.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function customerAttributes(): array
    {
        $attributes = $this->safe()->except(['first_profile_template', 'family', 'photo', 'remove_photo']);
        $attributes['discount_percent'] ??= 0;
        $attributes['is_active'] = $this->boolean('is_active', true);

        return $attributes;
    }

    /**
     * @return list<array{name: string, relation: string, phone: string|null, template: string}>
     */
    public function familyMembers(): array
    {
        return $this->validated('family') ?? [];
    }
}
