<?php

namespace App\Enums;

enum PurchaseStatus: string
{
    case Posted = 'posted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __(ucfirst($this->value));
    }
}
