<?php

namespace App\Enums;

enum MeasurementTemplate: string
{
    case Gents = 'gents';
    case Ladies = 'ladies';
    case Kids = 'kids';

    public function label(): string
    {
        return __(ucfirst($this->value));
    }
}
