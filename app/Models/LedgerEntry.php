<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A ledger line for a party. For suppliers, credit increases the amount payable and debit reduces it.
 */
#[Fillable([
    'party_type', 'party_id', 'entry_date', 'type', 'description', 'debit', 'credit',
    'reference_type', 'reference_id', 'reversed_at', 'user_id',
])]
class LedgerEntry extends Model
{
    public const OPENING = 'opening';

    public const PURCHASE = 'purchase';

    public const PAYMENT = 'payment';

    public const PURCHASE_RETURN = 'purchase_return';

    public const REVERSAL = 'reversal';

    public const SALE = 'sale';

    public const SALE_RETURN = 'sale_return';

    public const STITCHING_ORDER = 'stitching_order';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'reversed_at' => 'datetime',
        ];
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
    public function reference(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function typeLabel(): string
    {
        return ucwords(str_replace('_', ' ', $this->type));
    }
}
