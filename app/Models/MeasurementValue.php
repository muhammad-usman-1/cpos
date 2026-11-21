<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['measurement_id', 'measurement_field_id', 'value'])]
class MeasurementValue extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<MeasurementField, $this>
     */
    public function field(): BelongsTo
    {
        return $this->belongsTo(MeasurementField::class, 'measurement_field_id');
    }
}
