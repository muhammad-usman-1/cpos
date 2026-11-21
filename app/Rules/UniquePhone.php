<?php

namespace App\Rules;

use App\Services\PhoneDirectory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Rejects a phone number that already belongs to a customer, family member, supplier or staff member,
 * naming who uses it ("Already used by Rashid (Father of Ahmad Khan)").
 */
class UniquePhone implements ValidationRule
{
    /**
     * @param  string|null  $ignoreType  customer, profile, supplier or user: the record being edited
     */
    public function __construct(
        private ?string $ignoreType = null,
        private ?int $ignoreId = null,
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $match = app(PhoneDirectory::class)->find(is_scalar($value) ? (string) $value : null, $this->ignoreType, $this->ignoreId);

        if ($match) {
            $fail(__('This number is already used by :owner.', ['owner' => $match['owner']]));
        }
    }
}
