<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Http\Requests\SupplierPaymentRequest;
use App\Http\Requests\SupplierRequest;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Supplier;
use App\Services\LedgerService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function __construct(
        private LedgerService $ledger,
        private PaymentService $payments,
    ) {}

    public function index(Request $request): View
    {
        $suppliers = Supplier::query()
            ->search($request->query('q'))
            ->when($request->query('status') === 'payable', fn ($q) => $q->where('balance', '>', 0))
            ->when($request->query('status') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('suppliers.index', [
            'suppliers' => $suppliers,
            'totalPayable' => (float) Supplier::query()->where('balance', '>', 0)->sum('balance'),
        ]);
    }

    public function create(): View
    {
        return view('suppliers.form', ['supplier' => new Supplier(['is_active' => true])]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        $supplier = DB::transaction(function () use ($request): Supplier {
            $supplier = Supplier::create($request->safe()->except('opening_balance') + ['is_active' => $request->boolean('is_active', true)]);
            $opening = (float) $request->validated('opening_balance');

            if ($opening > 0) {
                $this->ledger->post($supplier, LedgerEntry::OPENING, 'Opening balance payable', 0, $opening);
            }

            return $supplier;
        });

        return redirect()->route('suppliers.show', $supplier)->with('success', __('Supplier added.'));
    }

    /**
     * Supplier profile with ledger and running balance (SUP02).
     */
    public function show(Request $request, Supplier $supplier): View
    {
        $filters = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);

        return view('suppliers.show', [
            'supplier' => $supplier,
            'statement' => $this->ledger->statement($supplier, $filters['from'] ?? null, $filters['to'] ?? null),
            'filters' => $filters,
            'recentPurchases' => $supplier->purchases()->latest('purchase_date')->latest('id')->limit(10)->get(),
            'recentPayments' => $supplier->payments()->with('user')->latest('payment_date')->latest('id')->limit(10)->get(),
        ]);
    }

    public function edit(Supplier $supplier): View
    {
        return view('suppliers.form', ['supplier' => $supplier]);
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->safe()->except('opening_balance') + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('suppliers.show', $supplier)->with('success', __('Supplier saved.'));
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        if ((float) $supplier->balance != 0.0) {
            return back()->with('error', __('This supplier has an outstanding balance. Settle it or mark the supplier inactive instead.'));
        }

        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', __('Supplier deleted. Their history is kept.'));
    }

    /**
     * Printable statement for any date range (SUP04).
     */
    public function statement(Request $request, Supplier $supplier): View
    {
        $filters = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);

        return view('suppliers.statement', [
            'supplier' => $supplier,
            'statement' => $this->ledger->statement($supplier, $filters['from'] ?? null, $filters['to'] ?? null),
            'filters' => $filters,
            'shopName' => Setting::get('shop_name'),
        ]);
    }

    public function createPayment(Supplier $supplier): View
    {
        return view('suppliers.payment', ['supplier' => $supplier, 'methods' => PaymentMethod::cases()]);
    }

    /**
     * Payment by cash, bank transfer, cheque or mobile wallet (SUP03).
     */
    public function storePayment(SupplierPaymentRequest $request, Supplier $supplier): RedirectResponse
    {
        $payment = $this->payments->paySupplier($supplier, $request->validated());

        return redirect()->route('suppliers.show', $supplier)->with('success', __('Payment :number recorded.', ['number' => $payment->number]));
    }

    public function cancelPayment(Request $request, Supplier $supplier, Payment $payment): RedirectResponse
    {
        abort_unless($payment->party_type === $supplier->getMorphClass() && (int) $payment->party_id === $supplier->id, 404);
        abort_if($payment->source_id !== null, 422, __('This payment was made with a purchase. Edit or cancel the purchase instead.'));

        $validated = $request->validate(['reason' => ['required', 'string', 'max:200']]);

        $this->payments->cancel($payment, $validated['reason']);

        return back()->with('success', __('Payment :number cancelled and reversed in the ledger.', ['number' => $payment->number]));
    }
}
