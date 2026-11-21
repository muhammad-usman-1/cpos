<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Shop expense (PAY04). The money leaves through a payment row, so it shows in the cash book or bank book.
 */
#[Fillable(['number', 'expense_category_id', 'expense_date', 'amount', 'method', 'paid_to', 'notes', 'created_by', 'cancelled_at', 'cancel_reason'])]
class Expense extends Model
{
    use Auditable, SoftDeletes;

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Expense $expense): void {
            $expense->forceFill(['number' => 'EXP-'.str_pad((string) $expense->id, 6, '0', STR_PAD_LEFT)])->saveQuietly();
        });
    }

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id')->withTrashed();
    }

    /**
     * @return MorphMany<Payment, $this>
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'source');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }
}
