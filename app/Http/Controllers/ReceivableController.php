<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\AccountsService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Customers with a pending balance and its ageing (PAY03), with payment reminders (PAY06).
 */
class ReceivableController extends Controller
{
    public function __construct(
        private AccountsService $accounts,
        private NotificationService $notifications,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:60'],
            'bucket' => ['nullable', 'in:'.implode(',', array_keys(AccountsService::BUCKETS))],
            'min' => ['nullable', 'numeric', 'min:0'],
        ]);

        $rows = $this->accounts->receivablesAgeing($filters['q'] ?? null)
            ->when($filters['min'] ?? null, fn ($rows, $min) => $rows->filter(fn ($row) => $row['balance'] >= (float) $min))
            ->when($filters['bucket'] ?? null, fn ($rows, $bucket) => $rows->filter(fn ($row) => $row['buckets'][$bucket] > 0))
            ->values();

        $totals = collect(array_keys(AccountsService::BUCKETS))->mapWithKeys(fn ($bucket) => [$bucket => round($rows->sum(fn ($row) => $row['buckets'][$bucket]), 2)]);

        return view('receivables.index', [
            'rows' => $rows,
            'totals' => $totals,
            'total' => round($rows->sum('balance'), 2),
            'buckets' => AccountsService::BUCKETS,
        ]);
    }

    public function remind(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customers' => ['required', 'array', 'min:1'],
            'customers.*' => ['integer'],
        ], ['customers.required' => __('Tick at least one customer.')]);

        $queued = Customer::query()
            ->whereIn('id', $validated['customers'])
            ->where('balance', '>', 0)
            ->get()
            ->filter(fn (Customer $customer) => $this->notifications->sendPaymentReminder($customer) !== null)
            ->count();

        return back()->with('success', __(':queued payment :value queued. Check the message log for delivery.', ['queued' => $queued, 'value' => str('reminder')->plural($queued)]));
    }
}
