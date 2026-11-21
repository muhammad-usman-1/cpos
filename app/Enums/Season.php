<?php

namespace App\Enums;

enum Season: string
{
    case Summer = 'summer';
    case Winter = 'winter';
    case MidSeason = 'mid_season';
    case Festive = 'festive';
    case AllSeason = 'all_season';

    public function label(): string
    {
        return __(ucwords(str_replace('_', ' ', $this->value)));
    }
}
