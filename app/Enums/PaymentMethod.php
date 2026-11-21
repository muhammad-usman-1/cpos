<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank';
    case Cheque = 'cheque';
    case MobileWallet = 'wallet';
    case Card = 'card';

    public function label(): string
    {
        return match ($this) {
            self::Cash => __('Cash'),
            self::BankTransfer => __('Bank transfer'),
            self::Cheque => __('Cheque'),
            self::MobileWallet => __('Mobile wallet'),
            self::Card => __('Card'),
        };
    }
}
