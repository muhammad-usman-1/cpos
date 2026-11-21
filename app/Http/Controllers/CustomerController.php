<?php

namespace App\Http\Controllers;

use App\Enums\CustomerTag;
use App\Enums\FamilyRelation;
use App\Enums\MeasurementTemplate;
use App\Enums\PaymentMethod;
use App\Http\Requests\CustomerRequest;
use App\Http\Requests\ReceivePaymentRequest;
use App\Models\Customer;
use App\Models\MeasurementField;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\LedgerService;
use App\Services\NotificationService;
use App\Services\PaymentService;
use App\Services\PhoneDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(
        private LedgerService $ledger,
        private PaymentService $payments,
        private NotificationService $notifications,
    ) {}

    public function index(Request $request): View
    {
        $customers = Customer::query()
            ->search($request->query('q'))
            ->when($request->query('filter') === 'balance', fn ($q) => $q->where('balance', '>', 0))
            ->when($request->query('tag'), fn ($q, $tag) => $q->where('tag', $tag))
            ->withCount(['orders as open_orders_count' => fn ($q) => $q->open()])
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'tags' => CustomerTag::cases(),
            'totalReceivable' => (float) Customer::query()->where('balance', '>', 0)->sum('balance'),
        ]);
    }

    /**
     * Instant search while typing (CUS02), used by the top search bar, POS and order screens.
     */
    public function lookup(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q'));

        if ($term === '') {
            return response()->json([]);
        }

        $isSingleLetter = mb_strlen($term) === 1 && ! ctype_digit($term);
        $startsWith = addcslashes($term, '%_\\').'%';

        $customers = Customer::query()
            ->when(
                $isSingleLetter,
                fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', $startsWith)
                    ->orWhereHas('profiles', fn ($profiles) => $profiles->where('name', 'like', $startsWith))),
                fn ($query) => $query->search($term),
            )
            ->where('is_active', true)
            ->with('profiles:id,customer_id,name,relation,phone,template')
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', [$startsWith])
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'phone', 'alt_phone', 'city', 'balance', 'tag', 'discount_percent']);

        return response()->json($customers->map(fn (Customer $customer): array => [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'city' => $customer->city,
            'balance' => (float) $customer->balance,
            'tag' => $customer->tag?->label(),
            'discount_percent' => (float) $customer->discount_percent,
            'url' => route('customers.show', $customer),
            'matched_member' => $this->matchedMember($customer, $term),
            'profiles' => $customer->profiles->map(fn ($profile) => ['id' => $profile->id, 'name' => $profile->label(), 'template' => $profile->template->value]),
        ]));
    }

    /**
     * When the search matched a family member rather than the account holder, say who: "Rashid (Father) · 0300…".
     */
    private function matchedMember(Customer $customer, string $term): ?string
    {
        $digits = (string) preg_replace('/[\s\-+()]/', '', $term);
        $isPhone = ctype_digit($digits) && strlen($digits) >= 3;

        $ownMatch = $isPhone
            ? str_contains((string) $customer->phone, $digits) || str_contains((string) $customer->alt_phone, $digits)
            : mb_stripos($customer->name, $term) !== false;

        if ($ownMatch) {
            return null;
        }

        $member = $customer->profiles->first(fn ($profile) => $isPhone
            ? str_contains((string) $profile->phone, $digits)
            : mb_stripos($profile->name, $term) !== false);

        return $member ? trim($member->label().' '.($member->phone ? '· '.$member->phone : '')) : null;
    }

    /**
     * Live "this number is already used by …" warning under every phone field (CUS05).
     */
    public function checkPhone(Request $request, PhoneDirectory $directory): JsonResponse
    {
        $match = $directory->find($request->query('phone'), $request->query('ignore_type'), $request->integer('ignore_id') ?: null);

        return response()->json($match
            ? ['exists' => true, 'message' => __('This number is already used by :owner.', ['owner' => $match['owner']]), 'url' => $match['url']]
            : ['exists' => false]);
    }

    public function create(Request $request): View
    {
        return view('customers.form', [
            'customer' => new Customer(['phone' => $request->query('phone'), 'is_active' => true]),
            'tags' => CustomerTag::cases(),
            'templates' => MeasurementTemplate::cases(),
            'relations' => FamilyRelation::familyMembers(),
        ]);
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $photoPath = $request->file('photo')?->store('customers', 'public');

        $customer = DB::transaction(function () use ($request, $photoPath): Customer {
            $customer = Customer::create($request->customerAttributes() + ['photo_path' => $photoPath, 'created_by' => $request->user()->id]);
            $customer->profiles()->create(['name' => 'Self', 'relation' => FamilyRelation::Self, 'template' => $request->validated('first_profile_template')]);

            foreach ($request->familyMembers() as $member) {
                $customer->profiles()->create($member);
            }

            return $customer;
        });

        $redirect = $request->input('return_to') === 'sale'
            ? route('sales.create', ['customer' => $customer->id])
            : route('customers.show', $customer);

        return redirect($redirect)->with('success', __('Customer :name added.', ['name' => $customer->name]));
    }

    /**
     * Everything about the customer on one screen (CUS03).
     */
    public function show(Request $request, Customer $customer): View
    {
        $customer->load(['profiles.currentMeasurement.values', 'profiles' => fn ($q) => $q->withCount('measurements')]);

        $filters = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        return view('customers.show', [
            'customer' => $customer,
            'fields' => MeasurementField::query()->where('is_active', true)->orderBy('group')->orderBy('sort_order')->get()->groupBy(fn ($field) => $field->template->value),
            'orders' => $customer->orders()->with('profile')->latest('id')->limit(20)->get(),
            'sales' => $request->user()->can('sales.view')
                ? $customer->sales()->with('items.variant.product', 'items.variant.colour')->where('status', 'completed')->latest('id')->limit(15)->get()
                : collect(),
            'payments' => $customer->payments()->with('user', 'source')->latest('payment_date')->latest('id')->limit(15)->get(),
            'statement' => $this->ledger->statement($customer, $filters['from'] ?? null, $filters['to'] ?? null),
            'filters' => $filters,
            'stats' => [
                'spent' => (float) $customer->sales()->where('status', 'completed')->sum('total'),
                'bills' => $customer->sales()->where('status', 'completed')->count(),
                'last_visit' => $customer->sales()->where('status', 'completed')->max('completed_at'),
                'open_orders' => $customer->orders()->open()->count(),
                'orders' => $customer->orders()->count(),
            ],
            'templates' => MeasurementTemplate::cases(),
            'relations' => FamilyRelation::familyMembers(),
        ]);
    }

    public function edit(Customer $customer): View
    {
        return view('customers.form', ['customer' => $customer, 'tags' => CustomerTag::cases(), 'templates' => MeasurementTemplate::cases(), 'relations' => FamilyRelation::familyMembers()]);
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $attributes = $request->customerAttributes();
        $oldPhoto = $customer->photo_path;

        if ($request->hasFile('photo')) {
            $attributes['photo_path'] = $request->file('photo')->store('customers', 'public');
        } elseif ($request->boolean('remove_photo')) {
            $attributes['photo_path'] = null;
        }

        $customer->update($attributes);

        if ($oldPhoto && $customer->photo_path !== $oldPhoto) {
            Storage::disk('public')->delete($oldPhoto);
        }

        return redirect()->route('customers.show', $customer)->with('success', __('Customer saved.'));
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        if ((float) $customer->balance != 0.0) {
            return back()->with('error', __('This customer has a balance. Settle it or mark the customer inactive instead.'));
        }

        if ($customer->orders()->open()->exists()) {
            return back()->with('error', __('This customer has open stitching orders.'));
        }

        $customer->delete();

        return redirect()->route('customers.index')->with('success', __('Customer deleted. Their history is kept.'));
    }

    public function createPayment(Customer $customer): View
    {
        return view('customers.payment', [
            'customer' => $customer,
            'methods' => PaymentMethod::cases(),
            'documents' => $this->payments->openDocuments($customer),
        ]);
    }

    /**
     * Receive money against the customer's balance and allocate it to bills and orders (PAY02).
     */
    public function storePayment(ReceivePaymentRequest $request, Customer $customer): RedirectResponse
    {
        $data = $request->validated();
        $payment = $this->payments->receiveOnAccount($customer, collect($data)->except('allocations')->all(), $data['allocations'] ?? []);
        $this->notifications->notifyPaymentReceived($payment, $customer);

        $allocated = (float) $payment->allocations()->sum('amount');
        $unallocated = round((float) $payment->amount - $allocated, 2);

        return redirect()->route('customers.show', $customer)->with('success', __('Payment :number of :amount received:value.', ['number' => $payment->number, 'amount' => number_format((float) $payment->amount, 2), 'value' => $unallocated > 0 ? ', '.number_format($unallocated, 2).' kept on account' : '']));
    }

    /**
     * Cancel a payment taken on account. Payments taken at a sale or on an order are corrected there.
     */
    public function cancelPayment(Request $request, Customer $customer, Payment $payment): RedirectResponse
    {
        abort_unless($payment->party_type === $customer->getMorphClass() && (int) $payment->party_id === $customer->id, 404);
        abort_if($payment->source_id !== null, 422, __('This payment belongs to a sale or order.'));

        $validated = $request->validate(['reason' => ['required', 'string', 'max:200']]);
        $this->payments->cancel($payment, $validated['reason']);

        return back()->with('success', __('Payment :number cancelled and reversed.', ['number' => $payment->number]));
    }

    /**
     * Printable account statement for any period (PAY01).
     */
    public function statement(Request $request, Customer $customer): View
    {
        $filters = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);

        return view('customers.statement', [
            'customer' => $customer,
            'statement' => $this->ledger->statement($customer, $filters['from'] ?? null, $filters['to'] ?? null),
            'filters' => $filters,
            'shopName' => Setting::get('shop_name'),
        ]);
    }
}
