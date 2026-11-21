<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pieces handed to a tailor and received back (ORD06), with an optional rate per piece (ORD11).
 */
#[Fillable(['stitching_order_id', 'tailor_id', 'pieces_given', 'given_at', 'pieces_received', 'received_at', 'rate_per_piece', 'notes', 'created_by'])]
class TailorAssignment extends Model
{
    use Auditable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'given_at' => 'datetime',
            'received_at' => 'datetime',
            'rate_per_piece' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function tailor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tailor_id')->withTrashed();
    }

    /**
     * @return BelongsTo<StitchingOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(StitchingOrder::class, 'stitching_order_id')->withTrashed();
    }

    public function earnings(): float
    {
        return round((float) $this->rate_per_piece * $this->pieces_received, 2);
    }
}
