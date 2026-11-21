<?php

namespace App\Enums;

enum RefundMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank';
    case Card = 'card';
    case MobileWallet = 'wallet';
    case CustomerBalance = 'balance';

    public function label(): string
    {
        return match ($this) {
            self::CustomerBalance => __('Adjust in customer balance (store credit)'),
            default => $this->paymentMethod()->label(),
        };
    }

    /**
     * The payment method used to pay the refund out, or null when the amount stays on the customer account.
     */
    public function paymentMethod(): ?PaymentMethod
    {
        return match ($this) {
            self::CustomerBalance => null,
            default => PaymentMethod::from($this->value),
        };
    }
}
