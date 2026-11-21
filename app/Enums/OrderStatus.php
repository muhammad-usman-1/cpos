<?php

namespace App\Enums;

/**
 * Stitching order lifecycle (ORD05).
 */
enum OrderStatus: string
{
    case Received = 'received';
    case Cutting = 'cutting';
    case Stitching = 'stitching';
    case Finishing = 'finishing';
    case Ready = 'ready';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __(ucfirst($this->value));
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Delivered, self::Cancelled], true);
    }

    /**
     * Statuses reachable from the status screen. Delivery and cancellation have their own steps.
     *
     * @return list<self>
     */
    public static function workflow(): array
    {
        return [self::Received, self::Cutting, self::Stitching, self::Finishing, self::Ready];
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Ready => 'badge-green',
            self::Delivered => 'badge-gray',
            self::Cancelled => 'badge-red',
            default => 'badge-amber',
        };
    }
}
