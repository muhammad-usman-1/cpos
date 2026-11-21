<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * One version of a profile's naap (MSR05). Versions are never edited; a change creates the next version.
 */
#[Fillable(['customer_profile_id', 'version', 'is_current', 'unit', 'notes', 'photo_path', 'taken_by'])]
class Measurement extends Model
{
    public const INCHES = 'in';

    public const CENTIMETRES = 'cm';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<CustomerProfile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class, 'customer_profile_id')->withTrashed();
    }

    /**
     * @return HasMany<MeasurementValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(MeasurementValue::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function takenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'taken_by')->withTrashed();
    }

    /**
     * Values keyed by measurement field id.
     *
     * @return array<int, string>
     */
    public function valueMap(): array
    {
        return $this->values->pluck('value', 'measurement_field_id')->all();
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    public function unitLabel(): string
    {
        return $this->unit === self::CENTIMETRES ? 'cm' : 'inches';
    }
}
