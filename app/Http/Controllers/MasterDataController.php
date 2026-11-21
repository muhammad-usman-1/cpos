<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Colour;
use App\Models\ExpenseCategory;
use App\Models\Fabric;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Categories, brands, fabrics and colours share one simple name list screen.
 */
class MasterDataController extends Controller
{
    /**
     * @var array<string, array{model: class-string<Model>, label: string, singular: string, foreign_key: string}>
     */
    public const TYPES = [
        'categories' => ['model' => Category::class, 'label' => 'Categories', 'singular' => 'category', 'foreign_key' => 'category_id'],
        'brands' => ['model' => Brand::class, 'label' => 'Brands', 'singular' => 'brand', 'foreign_key' => 'brand_id'],
        'fabrics' => ['model' => Fabric::class, 'label' => 'Fabrics', 'singular' => 'fabric', 'foreign_key' => 'fabric_id'],
        'colours' => ['model' => Colour::class, 'label' => 'Colours', 'singular' => 'colour', 'foreign_key' => 'colour_id'],
        'expense_categories' => ['model' => ExpenseCategory::class, 'label' => 'Expense types', 'singular' => 'expense type', 'foreign_key' => 'expense_category_id'],
    ];

    public function index(string $type): View
    {
        $config = $this->config($type);

        return view('masters.index', [
            'type' => $type,
            'config' => $config,
            'types' => self::TYPES,
            'records' => $config['model']::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $config = $this->config($type);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique($type, 'name')],
        ]);

        $config['model']::create(['name' => $validated['name'], 'is_active' => true]);

        return back()->with('success', __(':value added.', ['value' => ucfirst($config['singular'])]));
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        $config = $this->config($type);
        $record = $config['model']::query()->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique($type, 'name')->ignore($record->getKey())],
        ]);

        $record->update(['name' => $validated['name'], 'is_active' => $request->boolean('is_active')]);

        return back()->with('success', __(':value saved.', ['value' => ucfirst($config['singular'])]));
    }

    public function destroy(string $type, int $id): RedirectResponse
    {
        $config = $this->config($type);
        $record = $config['model']::query()->findOrFail($id);

        $record->delete();

        return back()->with('success', __(':value removed. Existing products keep their link.', ['value' => ucfirst($config['singular'])]));
    }

    /**
     * @return array{model: class-string<Model>, label: string, singular: string, foreign_key: string}
     */
    private function config(string $type): array
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return self::TYPES[$type];
    }
}
