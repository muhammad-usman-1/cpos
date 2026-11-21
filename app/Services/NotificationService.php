<?php

namespace App\Services;

use App\Enums\MessagingProvider;
use App\Enums\NotificationEvent;
use App\Jobs\SendNotification;
use App\Models\Customer;
use App\Models\MessageTemplate;
use App\Models\NotificationLog;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\StitchingOrder;
use App\Services\Messaging\GatewayResult;
use App\Services\Messaging\HttpGateway;
use App\Services\Messaging\LogGateway;
use App\Services\Messaging\MessageGateway;
use App\Services\Messaging\TwilioGateway;
use App\Services\Messaging\WhatsAppCloudGateway;
use Illuminate\Database\Eloquent\Model;

/**
 * Customer SMS / WhatsApp messages through the provider chosen in settings (NTF01, NTF02, PAY06).
 * Messages are written to the log first and sent by a queued job, so a slow provider never holds up the counter.
 */
class NotificationService
{
    /**
     * Queue a message for a customer. Automatic messages respect the on/off switch for their event.
     *
     * @param  array<string, string|int|float|null>  $values
     */
    public function notify(NotificationEvent $event, Customer $customer, array $values = [], ?Model $related = null, bool $automatic = true): ?NotificationLog
    {
        if (! Setting::usesMessaging() || ($automatic && ! Setting::enabled($event->settingKey()))) {
            return null;
        }

        if (! $customer->phone) {
            return null;
        }

        $log = NotificationLog::create([
            'event' => $event,
            'channel' => Setting::get('messaging_channel'),
            'provider' => Setting::get('messaging_provider'),
            'phone' => Customer::internationalPhone($customer->phone),
            'message' => $this->render($event, $customer, $values),
            'status' => NotificationLog::QUEUED,
            'customer_id' => $customer->id,
            'related_type' => $related?->getMorphClass(),
            'related_id' => $related?->getKey(),
            'created_by' => auth()->id(),
        ]);

        SendNotification::dispatch($log->id)->afterCommit();

        return $log;
    }

    public function notifyOrderReady(StitchingOrder $order): ?NotificationLog
    {
        return $this->notify(NotificationEvent::OrderReady, $order->customer, $this->orderValues($order), $order);
    }

    public function notifyDeliveryReminder(StitchingOrder $order): ?NotificationLog
    {
        return $this->notify(NotificationEvent::DeliveryReminder, $order->customer, $this->orderValues($order), $order);
    }

    public function notifyPaymentReceived(Payment $payment, Customer $customer): ?NotificationLog
    {
        return $this->notify(NotificationEvent::PaymentReceived, $customer->fresh(), [
            'amount' => number_format((float) $payment->amount, 0),
            'date' => $payment->payment_date->format('d M Y'),
        ], $payment);
    }

    public function sendPaymentReminder(Customer $customer): ?NotificationLog
    {
        return $this->notify(NotificationEvent::PaymentReminder, $customer, [], $customer, automatic: false);
    }

    /**
     * Send one queued message now. Used by the queue job and the "send test" button.
     */
    public function deliver(NotificationLog $log): GatewayResult
    {
        try {
            $result = $this->gateway($log->provider, $log->channel)->send($log->phone, $log->message);
        } catch (\Throwable $exception) {
            $result = GatewayResult::failed(mb_substr($exception->getMessage(), 0, 500));
        }

        $log->forceFill([
            'attempts' => $log->attempts + 1,
            'status' => $result->loggedOnly ? NotificationLog::LOGGED : ($result->delivered ? NotificationLog::SENT : NotificationLog::FAILED),
            'error' => $result->error,
            'provider_message_id' => $result->providerMessageId,
            'sent_at' => $result->delivered ? now() : null,
        ])->save();

        return $result;
    }

    /**
     * Send a test message straight away with the current settings.
     */
    public function sendTest(string $phone, string $message): NotificationLog
    {
        $log = NotificationLog::create([
            'event' => NotificationEvent::PaymentReminder,
            'channel' => Setting::get('messaging_channel'),
            'provider' => Setting::get('messaging_provider'),
            'phone' => Customer::internationalPhone($phone),
            'message' => $message,
            'status' => NotificationLog::QUEUED,
            'created_by' => auth()->id(),
        ]);

        $this->deliver($log);

        return $log->refresh();
    }

    /**
     * @param  array<string, string|int|float|null>  $values
     */
    public function render(NotificationEvent $event, Customer $customer, array $values = []): string
    {
        $language = $customer->preferred_language ?: Setting::get('message_language');
        $template = MessageTemplate::query()->where('event', $event)->where('language', $language)->value('body')
            ?? MessageTemplate::query()->where('event', $event)->where('language', 'en')->value('body')
            ?? 'Dear {customer_name}, message from {shop_name}.';

        return trim(MessageTemplate::render($template, $values + [
            'customer_name' => $customer->name,
            'shop_name' => Setting::get('shop_name'),
            'shop_phone' => Setting::get('shop_phone'),
            'currency' => Setting::get('currency'),
            'balance' => number_format(max((float) $customer->balance, 0), 0),
        ]));
    }

    public function gateway(?string $provider = null, ?string $channel = null): MessageGateway
    {
        $provider = MessagingProvider::tryFrom($provider ?? (string) Setting::get('messaging_provider')) ?? MessagingProvider::LogOnly;
        $channel ??= Setting::get('messaging_channel');

        return match ($provider) {
            MessagingProvider::LogOnly => new LogGateway,
            MessagingProvider::HttpGateway => new HttpGateway(
                (string) Setting::get('http_gateway_url'),
                (string) Setting::get('http_gateway_method'),
                Setting::secret('http_gateway_key'),
                Setting::get('http_gateway_sender'),
            ),
            MessagingProvider::Twilio => new TwilioGateway(
                (string) Setting::get('twilio_sid'),
                Setting::secret('twilio_token'),
                (string) Setting::get('twilio_from'),
                $channel === 'whatsapp',
            ),
            MessagingProvider::WhatsAppCloud => new WhatsAppCloudGateway(
                (string) Setting::get('whatsapp_phone_number_id'),
                Setting::secret('whatsapp_token'),
            ),
        };
    }

    /**
     * @return array<string, string>
     */
    private function orderValues(StitchingOrder $order): array
    {
        return [
            'order_number' => $order->number,
            'delivery_date' => $order->promised_date->format('d M Y'),
            'ready_date' => ($order->ready_at ?? now())->format('d M Y'),
            'order_balance' => number_format(max($order->balanceDue(), 0), 0),
        ];
    }
}
