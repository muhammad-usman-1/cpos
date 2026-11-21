<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Rules\UniquePhone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SupplierRequest extends FormRequest
{
    /**
     * Phones are stored in one format so the same number typed differently is still caught.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => Customer::normalizePhone($this->input('phone'))]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'shop_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'min:7', 'max:20', new UniquePhone('supplier', $this->route('supplier')?->id)],
            'city' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            'opening_balance' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ];
    }
}
