<?php

namespace App\Enums;

enum AdjustmentReason: string
{
    case Damage = 'damage';
    case Loss = 'loss';
    case Found = 'found';
    case Correction = 'correction';

    public function label(): string
    {
        return __(ucfirst($this->value));
    }

    /**
     * Whether the reason only makes sense for a stock decrease (true), increase (false) or both (null).
     */
    public function isDecrease(): ?bool
    {
        return match ($this) {
            self::Damage, self::Loss => true,
            self::Found => false,
            self::Correction => null,
        };
    }
}
