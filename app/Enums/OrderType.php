<?php

namespace App\Enums;

enum OrderType: string
{
    case Stitching = 'stitching';
    case Alteration = 'alteration';

    public function label(): string
    {
        return match ($this) {
            self::Stitching => __('Stitching'),
            self::Alteration => __('Alteration / repair'),
        };
    }
}
