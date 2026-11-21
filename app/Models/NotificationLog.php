<?php

namespace App\Models;

use App\Enums\NotificationEvent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Every SMS / WhatsApp message the system tried to send, with its delivery status.
 */
#[Fillable([
    'event', 'channel', 'provider', 'phone', 'message', 'status', 'error', 'provider_message_id',
    'customer_id', 'related_type', 'related_id', 'attempts', 'sent_at', 'created_by',
])]
class NotificationLog extends Model
{
    public const QUEUED = 'queued';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    /** Recorded but not sent, because no provider is configured. */
    public const LOGGED = 'logged';

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'event' => NotificationEvent::class,
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function related(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            self::SENT => 'badge-green',
            self::FAILED => 'badge-red',
            self::QUEUED => 'badge-amber',
            default => 'badge-gray',
        };
    }
}
