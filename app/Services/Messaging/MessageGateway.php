<?php

namespace App\Services\Messaging;

interface MessageGateway
{
    /**
     * Send one message. $phone is in international format without "+", for example 923001234567.
     */
    public function send(string $phone, string $message): GatewayResult;
}
