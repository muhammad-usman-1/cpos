<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Part of a customer payment applied to one credit sale or stitching order (PAY02).
 */
#[Fillable(['payment_id', 'allocatable_type', 'allocatable_id', 'amount'])]
class PaymentAllocation extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class)->withTrashed();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function allocatable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }
}
