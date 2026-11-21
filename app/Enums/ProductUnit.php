<?php

namespace App\Enums;

enum ProductUnit: string
{
    case Suit = 'suit';
    case Piece = 'piece';
    case Meter = 'meter';
    case Yard = 'yard';

    public function label(): string
    {
        return __(ucfirst($this->value));
    }

    /**
     * Loose fabric is sold by length, so fractional quantities are allowed.
     */
    public function isLooseFabric(): bool
    {
        return in_array($this, [self::Meter, self::Yard], true);
    }
}
