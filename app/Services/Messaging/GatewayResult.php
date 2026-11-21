<?php

namespace App\Services\Messaging;

final class GatewayResult
{
    public function __construct(
        public readonly bool $delivered,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $error = null,
        public readonly bool $loggedOnly = false,
    ) {}

    public static function sent(?string $providerMessageId = null): self
    {
        return new self(true, $providerMessageId);
    }

    public static function failed(string $error): self
    {
        return new self(false, null, $error);
    }
}
