<?php

namespace App\Enums;

enum ClothSource: string
{
    case Shop = 'shop';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Shop => __('Bought from shop'),
            self::Customer => __("Customer's own cloth"),
        };
    }
}
