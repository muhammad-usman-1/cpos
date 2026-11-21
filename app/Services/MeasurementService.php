<?php

namespace App\Services;

use App\Models\CustomerProfile;
use App\Models\Measurement;
use App\Models\MeasurementField;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Versioned naap (MSR05): saving a change creates the next version and marks it current; old versions stay as history.
 */
class MeasurementService
{
    /**
     * Save measurements for a profile. Returns the current version unchanged when nothing differs.
     *
     * @param  array<int|string, string|null>  $values  keyed by measurement field id
     */
    public function save(CustomerProfile $profile, array $values, string $unit, ?string $notes, ?UploadedFile $photo = null, bool $keepPhoto = true): Measurement
    {
        $storedPhoto = null;

        try {
            return DB::transaction(function () use ($profile, $values, $unit, $notes, $photo, $keepPhoto, &$storedPhoto): Measurement {
                CustomerProfile::query()->whereKey($profile->id)->lockForUpdate()->first();

                $fieldIds = MeasurementField::query()->where('template', $profile->template)->pluck('id')->all();
                $clean = collect($values)
                    ->only($fieldIds)
                    ->map(fn ($value) => trim((string) $value))
                    ->filter(fn ($value) => $value !== '')
                    ->sortKeys()
                    ->all();

                /** @var Measurement|null $current */
                $current = $profile->currentMeasurement()->with('values')->first();

                if ($current && ! $photo && $this->sameAs($current, $clean, $unit, $notes)) {
                    return $current;
                }

                if ($photo) {
                    $storedPhoto = $photo->store('measurements', 'public');
                }

                Measurement::query()->where('customer_profile_id', $profile->id)->update(['is_current' => false]);

                $measurement = Measurement::create([
                    'customer_profile_id' => $profile->id,
                    'version' => (int) Measurement::query()->where('customer_profile_id', $profile->id)->max('version') + 1,
                    'is_current' => true,
                    'unit' => $unit,
                    'notes' => $notes,
                    'photo_path' => $storedPhoto ?? ($keepPhoto ? $current?->photo_path : null),
                    'taken_by' => auth()->id(),
                ]);

                $measurement->values()->createMany(
                    collect($clean)->map(fn ($value, $fieldId) => ['measurement_field_id' => $fieldId, 'value' => $value])->values()->all(),
                );

                return $measurement->load('values');
            });
        } catch (\Throwable $exception) {
            if ($storedPhoto) {
                Storage::disk('public')->delete($storedPhoto);
            }

            throw $exception;
        }
    }

    /**
     * @param  array<int|string, string>  $values
     */
    private function sameAs(Measurement $current, array $values, string $unit, ?string $notes): bool
    {
        $existing = collect($current->valueMap())->map(fn ($value) => (string) $value)->sortKeys()->all();

        return $current->unit === $unit
            && trim((string) $current->notes) === trim((string) $notes)
            && $existing == $values;
    }

    /**
     * Fields for a profile's template grouped into body and style sections, in display order.
     *
     * @return Collection<string, Collection<int, MeasurementField>>
     */
    public function fieldsFor(CustomerProfile $profile): Collection
    {
        return MeasurementField::query()->forTemplate($profile->template)->get()->groupBy('group');
    }
}
