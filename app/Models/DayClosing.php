<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['closing_date', 'opening_cash', 'cash_in', 'cash_out', 'expected_cash', 'counted_cash', 'difference', 'notes', 'closed_by'])]
class DayClosing extends Model
{
    use Auditable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'closing_date' => 'date',
            'opening_cash' => 'decimal:2',
            'cash_in' => 'decimal:2',
            'cash_out' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by')->withTrashed();
    }
}
