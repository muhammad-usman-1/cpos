<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\Http;

/**
 * Meta WhatsApp Business Cloud API, plain text messages.
 * Meta only delivers free-form text inside the 24 hour window after the customer last messaged the shop;
 * outside it Meta requires pre-approved templates, and the API returns an error that is shown in the message log.
 */
class WhatsAppCloudGateway implements MessageGateway
{
    public function __construct(
        private string $phoneNumberId,
        private ?string $accessToken,
    ) {}

    public function send(string $phone, string $message): GatewayResult
    {
        if ($this->phoneNumberId === '' || ! $this->accessToken) {
            return GatewayResult::failed('WhatsApp phone number ID or access token is missing.');
        }

        $response = Http::timeout(20)
            ->withToken($this->accessToken)
            ->post("https://graph.facebook.com/v21.0/{$this->phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'text',
                'text' => ['preview_url' => false, 'body' => $message],
            ]);

        if ($response->failed()) {
            return GatewayResult::failed($response->json('error.message') ?? 'HTTP '.$response->status());
        }

        return GatewayResult::sent($response->json('messages.0.id'));
    }
}
