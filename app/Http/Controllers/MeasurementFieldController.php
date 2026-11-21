<?php

namespace App\Http\Controllers;

use App\Enums\MeasurementTemplate;
use App\Models\MeasurementField;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Owner configures the naap form per template (MSR04, MSR10).
 */
class MeasurementFieldController extends Controller
{
    public function index(MeasurementTemplate $template): View
    {
        return view('measurement-fields.index', [
            'template' => $template,
            'templates' => MeasurementTemplate::cases(),
            'fields' => MeasurementField::query()->where('template', $template)->orderBy('group')->orderBy('sort_order')->orderBy('id')->get()->groupBy('group'),
        ]);
    }

    public function store(Request $request, MeasurementTemplate $template): RedirectResponse
    {
        $validated = $this->validated($request);
        $key = Str::snake(Str::limit($validated['label'], 40, ''));

        if (MeasurementField::query()->where('template', $template)->where('key', $key)->exists()) {
            $key .= '_'.Str::lower(Str::random(4));
        }

        MeasurementField::create($validated + [
            'template' => $template,
            'key' => $key,
            'sort_order' => (int) MeasurementField::query()->where('template', $template)->max('sort_order') + 1,
            'is_active' => true,
        ]);

        return back()->with('success', __('Field added.'));
    }

    public function update(Request $request, MeasurementField $field): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['sort_order'] = $request->integer('sort_order', $field->sort_order);
        $validated['is_active'] = $request->boolean('is_active');

        $field->update($validated);

        return back()->with('success', __('Field saved.'));
    }

    /**
     * @return array{label: string, group: string, type: string, options: list<string>|null}
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'group' => ['required', Rule::in([MeasurementField::GROUP_BODY, MeasurementField::GROUP_STYLE])],
            'type' => ['required', Rule::in([MeasurementField::TYPE_NUMBER, MeasurementField::TYPE_SELECT, MeasurementField::TYPE_TEXT])],
            'options' => ['nullable', 'required_if:type,select', 'string', 'max:500'],
        ]);

        $validated['options'] = $validated['type'] === MeasurementField::TYPE_SELECT
            ? array_values(array_filter(array_map('trim', explode(',', (string) $validated['options']))))
            : null;

        return $validated;
    }
}
