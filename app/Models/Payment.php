<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'number', 'party_type', 'party_id', 'direction', 'payment_date', 'amount', 'method', 'reference_no', 'notes',
    'source_type', 'source_id', 'cancelled_at', 'user_id',
])]
class Payment extends Model
{
    use Auditable, SoftDeletes;

    public const OUT = 'out';

    public const IN = 'in';

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Payment $payment): void {
            $prefix = $payment->direction === self::OUT ? 'PV-' : 'RV-';
            $payment->forceFill(['number' => $prefix.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT)])->saveQuietly();
        });
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function party(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    /**
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * One-line description for the cash book. Expects party and source to be loaded.
     */
    public function describe(): string
    {
        $source = $this->source;

        $text = match (true) {
            $source instanceof Sale => 'Sale '.$source->number,
            $source instanceof SaleReturn => 'Refund for '.$source->number,
            $source instanceof StitchingOrder => ($this->direction === self::IN ? 'Order ' : 'Refund for ').$source->number,
            $source instanceof Purchase => 'Paid at purchase '.$source->number,
            $source instanceof Expense => 'Expense: '.$source->category?->name.($source->paid_to ? ' ('.$source->paid_to.')' : ''),
            $this->party instanceof Supplier => 'Payment to supplier',
            $this->party instanceof Customer => 'Received on account',
            default => $this->notes ?? 'Payment',
        };

        $partyName = $this->party?->name;

        return $partyName ? $text.' · '.$partyName : $text;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
