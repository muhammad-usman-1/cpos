<?php

namespace App\Models;

use App\Enums\MeasurementTemplate;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Owner-configurable naap fields per template (MSR04, MSR10). Removing a field deactivates it so history stays readable.
 */
#[Fillable(['template', 'key', 'label', 'group', 'type', 'options', 'sort_order', 'is_active'])]
class MeasurementField extends Model
{
    use Auditable;

    public const GROUP_BODY = 'body';

    public const GROUP_STYLE = 'style';

    public const TYPE_NUMBER = 'number';

    public const TYPE_SELECT = 'select';

    public const TYPE_TEXT = 'text';

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'template' => MeasurementTemplate::class,
            'options' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeForTemplate(Builder $query, MeasurementTemplate $template): void
    {
        $query->where('template', $template)->where('is_active', true)->orderBy('group')->orderBy('sort_order')->orderBy('id');
    }

    public function isNumeric(): bool
    {
        return $this->type === self::TYPE_NUMBER;
    }
}
