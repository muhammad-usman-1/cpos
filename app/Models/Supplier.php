<?php

namespace App\Models;

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

#[Fillable(['name', 'shop_name', 'phone', 'city', 'address', 'notes', 'is_active'])]
class Supplier extends Model implements LedgerParty
{
    use Auditable, HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Stored in one format so the same number is caught across customers, suppliers and staff.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function phone(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => Customer::normalizePhone($value));
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
     * @return HasMany<Purchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $like = '%'.$term.'%';

        $query->where(fn (Builder $q) => $q->where('name', 'like', $like)
            ->orWhere('shop_name', 'like', $like)
            ->orWhere('phone', 'like', $like)
            ->orWhere('city', 'like', $like));
    }

    /**
     * Credit (purchases) increases what the shop owes; debit (payments, returns) reduces it.
     */
    public function ledgerBalance(float $debit, float $credit): float
    {
        return $credit - $debit;
    }

    public function displayName(): string
    {
        return $this->shop_name ? $this->name.' ('.$this->shop_name.')' : $this->name;
    }
}
