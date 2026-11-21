<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PurchaseStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'number', 'supplier_id', 'purchase_date', 'invoice_no', 'subtotal', 'discount', 'other_charges', 'total',
    'paid_amount', 'payment_method', 'status', 'notes', 'attachment_path', 'created_by', 'cancelled_at',
    'cancelled_by', 'cancel_reason',
])]
class Purchase extends Model
{
    use Auditable, SoftDeletes;

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'other_charges' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'status' => PurchaseStatus::class,
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Purchase $purchase): void {
            $purchase->forceFill(['number' => 'PUR-'.str_pad((string) $purchase->id, 6, '0', STR_PAD_LEFT)])->saveQuietly();
        });
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    /**
     * @return HasMany<PurchaseItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /**
     * @return HasMany<PurchaseReturn, $this>
     */
    public function returns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class);
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
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by')->withTrashed();
    }

    public function isCancelled(): bool
    {
        return $this->status === PurchaseStatus::Cancelled;
    }

    /**
     * Purchases with returns are locked, because editing them would break the returned quantities.
     */
    public function isEditable(): bool
    {
        return ! $this->isCancelled() && ! $this->returns()->exists();
    }

    public function balanceDue(): float
    {
        return round((float) $this->total - (float) $this->paid_amount, 2);
    }

    public function attachmentUrl(): ?string
    {
        return $this->attachment_path ? Storage::disk('public')->url($this->attachment_path) : null;
    }
}
