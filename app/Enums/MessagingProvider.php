<?php

namespace App\Enums;

enum MessagingProvider: string
{
    case LogOnly = 'log';
    case HttpGateway = 'http';
    case Twilio = 'twilio';
    case WhatsAppCloud = 'whatsapp_cloud';

    public function label(): string
    {
        return match ($this) {
            self::LogOnly => __('Not sending (record in log only)'),
            self::HttpGateway => __('SMS gateway (any provider with an HTTP API)'),
            self::Twilio => __('Twilio (SMS or WhatsApp)'),
            self::WhatsAppCloud => __('WhatsApp Business Cloud API (Meta)'),
        };
    }
}
