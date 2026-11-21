<?php

namespace App\Services\Messaging;

/**
 * Used until a provider is configured: the message is kept in the log but not sent.
 */
class LogGateway implements MessageGateway
{
    public function send(string $phone, string $message): GatewayResult
    {
        return new GatewayResult(false, null, null, true);
    }
}
