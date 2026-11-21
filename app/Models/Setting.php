<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    private const CACHE_KEY = 'settings.all';

    /**
     * Default values used until the owner saves a setting.
     *
     * @var array<string, string>
     */
    public const DEFAULTS = [
        'shop_name' => 'Clothing Shop',
        'shop_phone' => '',
        'idle_timeout_minutes' => '30',
        'allow_negative_stock' => '0',
        'use_barcodes' => '0',
        'use_messaging' => '0',
        'currency' => 'PKR',
        'country_code' => '92',
        'receipt_footer' => 'Thank you for shopping with us.',
        'shop_address' => '',
        'shop_website' => '',
        'shop_tax_number' => '',
        'receipt_header_note' => '',
        'receipt_policies' => "Exchange within 7 days with this receipt only.\nNo cash refund. Exchange or store credit only.\nUnstitched fabric is exchanged only if uncut and unwashed.\nNo exchange or return on sale and discounted items.",
        'exchange_days' => '7',
        'receipt_show_savings' => '1',
        'receipt_show_item_count' => '1',
        'receipt_show_balance' => '1',
        'order_slip_terms' => "Please bring this slip when collecting your order.\nAdvance is not refundable once cutting has started.\nChanges after cutting are charged extra.\nThe shop is not responsible for orders not collected within 30 days of the delivery date.",
        'header_logo' => '',
        'bill_logo' => '',
        'cash_opening_balance' => '0',
        'bank_opening_balance' => '0',
        'messaging_provider' => 'log',
        'messaging_channel' => 'sms',
        'message_language' => 'en',
        'http_gateway_url' => '',
        'http_gateway_method' => 'GET',
        'http_gateway_sender' => '',
        'twilio_sid' => '',
        'twilio_from' => '',
        'whatsapp_phone_number_id' => '',
        'notify_order_ready' => '1',
        'notify_delivery_reminder' => '1',
        'notify_payment_received' => '1',
        'notify_payment_reminder' => '1',
        'reminder_after_days' => '3',
    ];

    /**
     * Credentials are stored encrypted and never written to the audit log.
     *
     * @var list<string>
     */
    public const SECRET_KEYS = ['http_gateway_key', 'twilio_token', 'whatsapp_token'];

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    public static function get(string $key, ?string $default = null): ?string
    {
        $all = Cache::rememberForever(self::CACHE_KEY, fn (): array => static::query()->pluck('value', 'key')->all());

        return $all[$key] ?? $default ?? self::DEFAULTS[$key] ?? null;
    }

    /**
     * Decrypted value of a credential, or null when not set.
     */
    public static function secret(string $key): ?string
    {
        $value = static::get($key);

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }

    public static function hasSecret(string $key): bool
    {
        return static::secret($key) !== null;
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            $isSecret = in_array($key, self::SECRET_KEYS, true);

            if ($isSecret && ($value === null || $value === '')) {
                continue;
            }

            $setting = static::query()->findOrNew($key);
            $old = $setting->exists ? $setting->value : null;

            if (! $isSecret && $setting->exists && $old === $value) {
                continue;
            }

            $setting->fill(['key' => $key, 'value' => $isSecret ? Crypt::encryptString($value) : $value])->save();
            AuditLog::record('setting_changed', null, [$key => $isSecret ? '••••' : $old], [$key => $isSecret ? 'changed' : $value]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Logo in the top bar and on the login pages, or null when none is uploaded.
     */
    public static function headerLogoUrl(): ?string
    {
        return static::logoUrl('header_logo');
    }

    /**
     * Logo printed on receipts, slips, vouchers and statements, or null when none is uploaded.
     */
    public static function billLogoUrl(): ?string
    {
        return static::logoUrl('bill_logo');
    }

    /**
     * A multi-line setting (policies, terms) as a list of its non-empty lines.
     *
     * @return list<string>
     */
    public static function lines(string $key): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) static::get($key)) ?: []), fn (string $line) => $line !== ''));
    }

    public static function isOn(string $key): bool
    {
        return static::get($key) === '1';
    }

    private static function logoUrl(string $key): ?string
    {
        $path = static::get($key);

        return $path ? Storage::disk('public')->url($path) : null;
    }

    public static function idleTimeoutMinutes(): int
    {
        return (int) static::get('idle_timeout_minutes');
    }

    public static function allowsNegativeStock(): bool
    {
        return static::get('allow_negative_stock') === '1';
    }

    /**
     * Barcode labels, scanning hints and barcodes on printouts. Off for shops without a scanner.
     */
    public static function usesBarcodes(): bool
    {
        return static::get('use_barcodes') === '1';
    }

    /**
     * SMS / WhatsApp messages, the message log and WhatsApp buttons. Off until the shop wants it.
     */
    public static function usesMessaging(): bool
    {
        return static::get('use_messaging') === '1';
    }

    public static function enabled(string $key): bool
    {
        return static::get($key) === '1';
    }
}
