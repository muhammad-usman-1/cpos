<?php

namespace App\Models;

use App\Enums\FamilyRelation;
use App\Enums\MeasurementTemplate;
use App\Models\Concerns\Auditable;
use App\Rules\UniquePhone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;

/**
 * A family member under one customer account (CUS04), each with their own measurements.
 */
#[Fillable(['customer_id', 'name', 'relation', 'phone', 'template', 'notes'])]
class CustomerProfile extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    /**
     * @return array<string, class-string>
     */
    protected function casts(): array
    {
        return [
            'template' => MeasurementTemplate::class,
            'relation' => FamilyRelation::class,
        ];
    }

    /**
     * Stored in the same format as customer phones so duplicates are found (CUS05).
     *
     * @return Attribute<string|null, string|null>
     */
    protected function phone(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => Customer::normalizePhone($value));
    }

    /**
     * Rules for a person's details. The account holder (Self) has no relation or phone of their own,
     * so those fields are left out entirely; a family member's phone is optional.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function detailRules(Customer $customer, ?self $profile = null): array
    {
        $isAccountHolder = $profile?->relation === FamilyRelation::Self;

        return [
            'name' => ['required', 'string', 'max:80'],
            'relation' => $isAccountHolder
                ? ['exclude']
                : ['nullable', Rule::enum(FamilyRelation::class), Rule::notIn([FamilyRelation::Self->value])],
            'phone' => $isAccountHolder
                ? ['exclude']
                : ['nullable', 'string', 'min:7', 'max:20', Rule::notIn(array_filter([$customer->phone, $customer->alt_phone])), new UniquePhone('profile', $profile?->id)],
            'template' => ['required', Rule::enum(MeasurementTemplate::class)],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function detailMessages(): array
    {
        return ['phone.not_in' => __('This number is already the customer\'s own phone.')];
    }

    /**
     * "Rashid (Father)", or just the name for the account holder and for older profiles named after the relation.
     */
    public function label(): string
    {
        if (! $this->relation || $this->relation === FamilyRelation::Self || mb_strtolower($this->name) === $this->relation->value) {
            return $this->relation && mb_strtolower($this->name) === $this->relation->value ? $this->relation->label() : $this->name;
        }

        return $this->name.' ('.$this->relation->label().')';
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * @return HasMany<Measurement, $this>
     */
    public function measurements(): HasMany
    {
        return $this->hasMany(Measurement::class)->orderByDesc('version');
    }

    /**
     * @return HasOne<Measurement, $this>
     */
    public function currentMeasurement(): HasOne
    {
        return $this->hasOne(Measurement::class)->where('is_current', true);
    }
}
