<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Records every create, update, delete and restore of the model in the audit log (AUTH05).
 *
 * @mixin Model
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            AuditLog::record('created', $model, null, $model->auditableValues($model->getAttributes()));
        });

        static::updated(function (Model $model): void {
            $changes = $model->auditableValues($model->getChanges());

            if ($changes === []) {
                return;
            }

            $old = array_intersect_key($model->getOriginal(), $changes);

            AuditLog::record('updated', $model, $old, $changes);
        });

        static::deleted(function (Model $model): void {
            AuditLog::record('deleted', $model, $model->auditableValues($model->getAttributes()), null);
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model): void {
                AuditLog::record('restored', $model, null, null);
            });
        }
    }

    /**
     * Strip timestamps and hidden attributes (passwords, tokens) from audited values.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function auditableValues(array $values): array
    {
        $excluded = array_merge($this->getHidden(), ['created_at', 'updated_at', 'deleted_at']);

        return array_diff_key($values, array_flip($excluded));
    }
}
