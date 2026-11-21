<?php

namespace Database\Seeders;

use App\Enums\NotificationEvent;
use App\Models\ExpenseCategory;
use App\Models\MessageTemplate;
use Illuminate\Database\Seeder;

/**
 * Default English and Urdu message texts (NTF02) and expense categories (PAY04). Safe to re-run: edited texts are kept.
 */
class MessageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            NotificationEvent::OrderReady->value => [
                'en' => 'Dear {customer_name}, your order {order_number} is ready for collection at {shop_name}. Balance due: {currency} {order_balance}. Thank you.',
                'ur' => 'محترم {customer_name}، آپ کا آرڈر {order_number} تیار ہے۔ براہ کرم {shop_name} سے وصول کر لیں۔ بقایا رقم: {currency} {order_balance}۔ شکریہ',
            ],
            NotificationEvent::DeliveryReminder->value => [
                'en' => 'Reminder from {shop_name}: your order {order_number} has been ready since {ready_date}. Please collect it at your convenience. Balance: {currency} {order_balance}.',
                'ur' => '{shop_name} کی طرف سے یاد دہانی: آپ کا آرڈر {order_number} {ready_date} سے تیار ہے۔ براہ کرم وصول کر لیں۔ بقایا: {currency} {order_balance}',
            ],
            NotificationEvent::PaymentReceived->value => [
                'en' => 'Thank you {customer_name}. {shop_name} received {currency} {amount} on {date}. Remaining balance: {currency} {balance}.',
                'ur' => 'شکریہ {customer_name}۔ {shop_name} کو {date} کو {currency} {amount} موصول ہوئے۔ باقی رقم: {currency} {balance}',
            ],
            NotificationEvent::PaymentReminder->value => [
                'en' => 'Dear {customer_name}, your outstanding balance at {shop_name} is {currency} {balance}. Kindly clear it at your earliest. Thank you. {shop_phone}',
                'ur' => 'محترم {customer_name}، {shop_name} پر آپ کا بقایا {currency} {balance} ہے۔ براہ کرم جلد ادائیگی فرمائیں۔ شکریہ {shop_phone}',
            ],
        ];

        foreach ($templates as $event => $languages) {
            foreach ($languages as $language => $body) {
                MessageTemplate::query()->firstOrCreate(['event' => $event, 'language' => $language], ['body' => $body]);
            }
        }

        foreach (['Rent', 'Electricity', 'Salary', 'Tea and refreshments', 'Transport', 'Repairs', 'Other'] as $name) {
            ExpenseCategory::query()->withTrashed()->firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
