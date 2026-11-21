<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\Http;

/**
 * Twilio Messages API. For WhatsApp, the sender is a Twilio WhatsApp number and both numbers get a "whatsapp:" prefix.
 */
class TwilioGateway implements MessageGateway
{
    public function __construct(
        private string $accountSid,
        private ?string $authToken,
        private string $from,
        private bool $whatsapp,
    ) {}

    public function send(string $phone, string $message): GatewayResult
    {
        if ($this->accountSid === '' || ! $this->authToken || $this->from === '') {
            return GatewayResult::failed('Twilio account SID, auth token or sender number is missing.');
        }

        $prefix = $this->whatsapp ? 'whatsapp:' : '';
        $from = $prefix.'+'.ltrim(str_replace('whatsapp:', '', $this->from), '+');

        $response = Http::timeout(20)
            ->withBasicAuth($this->accountSid, $this->authToken)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json", [
                'From' => $from,
                'To' => $prefix.'+'.$phone,
                'Body' => $message,
            ]);

        if ($response->failed()) {
            return GatewayResult::failed($response->json('message') ?? 'HTTP '.$response->status());
        }

        return GatewayResult::sent($response->json('sid'));
    }
}
