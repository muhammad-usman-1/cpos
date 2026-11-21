<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\Http;

/**
 * Generic SMS gateway: most local providers take an API key, sender, number and text as URL parameters.
 * The owner pastes the provider's URL with placeholders, for example:
 *   https://sms.example.pk/api/send?key={key}&sender={sender}&to={phone}&msg={message}
 */
class HttpGateway implements MessageGateway
{
    public function __construct(
        private string $url,
        private string $method,
        private ?string $key,
        private ?string $sender,
    ) {}

    public function send(string $phone, string $message): GatewayResult
    {
        if ($this->url === '') {
            return GatewayResult::failed('SMS gateway URL is not set.');
        }

        $values = ['{phone}' => $phone, '{message}' => $message, '{key}' => (string) $this->key, '{sender}' => (string) $this->sender];

        $request = Http::timeout(20)->acceptJson();

        if (strtoupper($this->method) === 'POST') {
            [$endpoint, $query] = array_pad(explode('?', $this->url, 2), 2, '');
            parse_str($query, $params);
            $params = array_map(fn ($value) => strtr((string) $value, $values), $params);
            $response = $request->asForm()->post($endpoint, $params);
        } else {
            $url = strtr($this->url, array_map('rawurlencode', $values));
            $response = $request->get($url);
        }

        if ($response->failed()) {
            return GatewayResult::failed('HTTP '.$response->status().': '.mb_substr($response->body(), 0, 300));
        }

        return GatewayResult::sent(mb_substr($response->body(), 0, 190));
    }
}
