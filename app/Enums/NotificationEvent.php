<?php

namespace App\Enums;

/**
 * Customer messages the shop can send (NTF01, PAY06). Each has an English and an Urdu template.
 */
enum NotificationEvent: string
{
    case OrderReady = 'order_ready';
    case DeliveryReminder = 'delivery_reminder';
    case PaymentReceived = 'payment_received';
    case PaymentReminder = 'payment_reminder';

    public function label(): string
    {
        return match ($this) {
            self::OrderReady => __('Order ready'),
            self::DeliveryReminder => __('Collection reminder'),
            self::PaymentReceived => __('Payment received'),
            self::PaymentReminder => __('Payment reminder'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::OrderReady => __('Sent automatically when an order is marked Ready.'),
            self::DeliveryReminder => __('Sent daily for ready orders not collected after the set number of days.'),
            self::PaymentReceived => __('Sent when a customer pays against their balance or an order.'),
            self::PaymentReminder => __('Sent from the receivables screen to customers with a balance.'),
        };
    }

    /**
     * Setting that switches automatic sending of this message on or off.
     */
    public function settingKey(): string
    {
        return 'notify_'.$this->value;
    }

    /**
     * @return list<string>
     */
    public function placeholders(): array
    {
        $common = ['customer_name', 'shop_name', 'shop_phone', 'currency', 'balance'];

        return match ($this) {
            self::OrderReady => [...$common, 'order_number', 'delivery_date', 'order_balance'],
            self::DeliveryReminder => [...$common, 'order_number', 'ready_date', 'order_balance'],
            self::PaymentReceived => [...$common, 'amount', 'date'],
            self::PaymentReminder => $common,
        };
    }
}
