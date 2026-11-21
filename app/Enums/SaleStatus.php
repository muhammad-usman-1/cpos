<?php

namespace App\Enums;

enum SaleStatus: string
{
    case Held = 'held';
    case Completed = 'completed';

    public function label(): string
    {
        return __(ucfirst($this->value));
    }
}
