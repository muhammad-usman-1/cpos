<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(): View
    {
        return view('settings.edit', [
            'settings' => collect(Setting::DEFAULTS)->mapWithKeys(fn ($default, $key) => [$key => Setting::get($key)]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'shop_name' => ['required', 'string', 'max:100'],
            'shop_phone' => ['nullable', 'string', 'max:60'],
            'currency' => ['required', 'string', 'max:10'],
            'idle_timeout_minutes' => ['required', 'integer', 'min:0', 'max:720'],
            'country_code' => ['required', 'digits_between:1,4'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
            'shop_address' => ['nullable', 'string', 'max:255'],
            'shop_website' => ['nullable', 'string', 'max:120'],
            'shop_tax_number' => ['nullable', 'string', 'max:60'],
            'receipt_header_note' => ['nullable', 'string', 'max:160'],
            'receipt_policies' => ['nullable', 'string', 'max:2000'],
            'exchange_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'order_slip_terms' => ['nullable', 'string', 'max:2000'],
            'header_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'bill_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_header_logo' => ['boolean'],
            'remove_bill_logo' => ['boolean'],
        ], [
            'header_logo.max' => __('The logo must not be larger than 2 MB.'),
            'bill_logo.max' => __('The logo must not be larger than 2 MB.'),
        ]);

        foreach (['header_logo', 'bill_logo'] as $logo) {
            $this->saveLogo($request, $logo);
        }

        Setting::put([
            'shop_name' => $validated['shop_name'],
            'shop_phone' => trim((string) ($validated['shop_phone'] ?? '')),
            'currency' => $validated['currency'],
            'country_code' => $validated['country_code'],
            'receipt_footer' => $validated['receipt_footer'] ?? '',
            'shop_address' => trim((string) ($validated['shop_address'] ?? '')),
            'shop_website' => trim((string) ($validated['shop_website'] ?? '')),
            'shop_tax_number' => trim((string) ($validated['shop_tax_number'] ?? '')),
            'receipt_header_note' => trim((string) ($validated['receipt_header_note'] ?? '')),
            'receipt_policies' => trim((string) ($validated['receipt_policies'] ?? '')),
            'exchange_days' => (string) ($validated['exchange_days'] ?? 0),
            'order_slip_terms' => trim((string) ($validated['order_slip_terms'] ?? '')),
            'receipt_show_savings' => $request->boolean('receipt_show_savings') ? '1' : '0',
            'receipt_show_item_count' => $request->boolean('receipt_show_item_count') ? '1' : '0',
            'receipt_show_balance' => $request->boolean('receipt_show_balance') ? '1' : '0',
            'idle_timeout_minutes' => (string) $validated['idle_timeout_minutes'],
            'allow_negative_stock' => $request->boolean('allow_negative_stock') ? '1' : '0',
            'use_barcodes' => $request->boolean('use_barcodes') ? '1' : '0',
            'use_messaging' => $request->boolean('use_messaging') ? '1' : '0',
        ]);

        return back()->with('success', __('Settings saved.'));
    }

    /**
     * Store a new logo (replacing the old file) or remove it when "Remove" is ticked.
     */
    private function saveLogo(Request $request, string $key): void
    {
        $oldPath = Setting::get($key);

        if ($request->hasFile($key)) {
            Setting::put([$key => $request->file($key)->store('logos', 'public')]);
        } elseif ($request->boolean('remove_'.$key)) {
            Setting::put([$key => '']);
        } else {
            return;
        }

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }
    }
}
