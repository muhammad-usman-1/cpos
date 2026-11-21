<?php

namespace App\Models;

use App\Enums\CustomerTag;
use App\Models\Concerns\Auditable;
use App\Models\Contracts\LedgerParty;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'name', 'phone', 'alt_phone', 'address', 'photo_path', 'city', 'notes', 'tag', 'discount_percent', 'preferred_language', 'birthday', 'anniversary',
    'is_active', 'created_by',
])]
class Customer extends Model implements LedgerParty
{
    use Auditable, HasFactory, SoftDeletes;

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'tag' => CustomerTag::class,
            'discount_percent' => 'decimal:2',
            'birthday' => 'date',
            'anniversary' => 'date',
            'balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Store phone numbers in one format so "0300-1234567", "+92 300 1234567" and "03001234567" match (CUS05).
     */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        $countryCode = (string) Setting::get('country_code');

        if ($countryCode !== '' && str_starts_with($digits, $countryCode) && strlen($digits) === strlen($countryCode) + 10) {
            return '0'.substr($digits, strlen($countryCode));
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '3')) {
            return '0'.$digits;
        }

        return $digits;
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function phone(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => static::normalizePhone($value));
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function altPhone(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => static::normalizePhone($value));
    }

    /**
     * International format for WhatsApp links, for example 923001234567.
     */
    public function whatsappNumber(): string
    {
        return static::internationalPhone((string) $this->phone);
    }

    /**
     * 03001234567 becomes 923001234567, as SMS and WhatsApp providers expect.
     */
    public static function internationalPhone(string $phone): string
    {
        $phone = (string) static::normalizePhone($phone);

        return str_starts_with($phone, '0') ? Setting::get('country_code').substr($phone, 1) : $phone;
    }

    /**
     * @return HasMany<CustomerProfile, $this>
     */
    public function profiles(): HasMany
    {
        return $this->hasMany(CustomerProfile::class)->orderBy('id');
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * @return HasMany<StitchingOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(StitchingOrder::class);
    }

    /**
     * @return MorphMany<LedgerEntry, $this>
     */
    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(LedgerEntry::class, 'party');
    }

    /**
     * @return MorphMany<Payment, $this>
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'party');
    }

    /**
     * Debit (credit sales, stitching charges) increases what the customer owes; credit (payments, returns) reduces it.
     */
    public function ledgerBalance(float $debit, float $credit): float
    {
        return $debit - $credit;
    }

    /**
     * Instant search by phone or name (CUS02). Phone digits match from the start, names match anywhere.
     *
     * @param  Builder<self>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $digits = (string) preg_replace('/[\s\-+()]/', '', $term);
        $isPhone = ctype_digit($digits) && strlen($digits) >= 3;

        $query->where(function (Builder $query) use ($term, $digits, $isPhone): void {
            if ($isPhone) {
                $normalized = static::normalizePhone($digits) ?? $digits;
                $query->where('phone', 'like', $normalized.'%')
                    ->orWhere('phone', 'like', '%'.$digits.'%')
                    ->orWhere('alt_phone', 'like', '%'.$digits.'%')
                    ->orWhereHas('profiles', fn (Builder $profiles) => $profiles->where('phone', 'like', '%'.$digits.'%'));
            } else {
                $query->where('name', 'like', '%'.$term.'%')
                    ->orWhereHas('profiles', fn (Builder $profiles) => $profiles->where('name', 'like', '%'.$term.'%'));
            }
        });
    }
}
