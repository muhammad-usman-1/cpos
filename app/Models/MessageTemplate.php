<?php

namespace App\Models;

use App\Enums\NotificationEvent;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Editable message text per event and language (NTF02). Placeholders look like {customer_name}.
 */
#[Fillable(['event', 'language', 'body'])]
class MessageTemplate extends Model
{
    use Auditable;

    public const LANGUAGES = ['en' => 'English', 'ur' => 'اردو (Urdu)'];

    /**
     * @return array<string, class-string>
     */
    protected function casts(): array
    {
        return [
            'event' => NotificationEvent::class,
        ];
    }

    /**
     * @param  array<string, string|int|float|null>  $values
     */
    public static function render(string $body, array $values): string
    {
        return strtr($body, collect($values)->mapWithKeys(fn ($value, $key) => ['{'.$key.'}' => (string) $value])->all());
    }
}
