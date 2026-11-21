<?php

namespace App\Enums;

enum CustomerTag: string
{
    case Regular = 'regular';
    case Wholesale = 'wholesale';
    case Vip = 'vip';

    public function label(): string
    {
        return match ($this) {
            self::Regular => __('Regular'),
            self::Wholesale => __('Wholesale'),
            self::Vip => __('VIP'),
        };
    }
}
