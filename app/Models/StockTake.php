<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'status', 'notes', 'created_by', 'posted_by', 'posted_at'])]
class StockTake extends Model
{
    use Auditable;

    public const DRAFT = 'draft';

    public const POSTED = 'posted';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (StockTake $stockTake): void {
            $stockTake->forceFill(['number' => 'ST-'.str_pad((string) $stockTake->id, 5, '0', STR_PAD_LEFT)])->saveQuietly();
        });
    }

    /**
     * @return HasMany<StockTakeItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(StockTakeItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by')->withTrashed();
    }

    public function isPosted(): bool
    {
        return $this->status === self::POSTED;
    }
}
