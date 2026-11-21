<?php

namespace App\Http\Controllers;

use App\Enums\ClothSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Http\Requests\StitchingOrderRequest;
use App\Models\Customer;
use App\Models\Measurement;
use App\Models\Setting;
use App\Models\StitchingOrder;
use App\Models\User;
use App\Services\MeasurementService;
use App\Services\NotificationService;
use App\Services\StitchingOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StitchingOrderController extends Controller
{
    public function __construct(
        private StitchingOrderService $orders,
        private MeasurementService $measurements,
        private NotificationService $notifications,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', 'string'],
            'tailor_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $orders = StitchingOrder::query()
            ->visibleTo($request->user())
            ->with('customer', 'profile', 'tailor')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w->where('number', 'like', "%$term%")
                ->orWhereHas('customer', fn ($c) => $c->search($term))))
            ->when(($filters['status'] ?? 'open') === 'open', fn ($q) => $q->open())
            ->when(! in_array($filters['status'] ?? 'open', ['open', 'all'], true), fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['tailor_id'] ?? null, fn ($q, $id) => $q->where('tailor_id', $id))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('promised_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('promised_date', '<=', $to))
            ->orderBy('promised_date')
            ->orderBy('id')
            ->paginate(30)
            ->withQueryString();

        return view('orders.index', [
            'orders' => $orders,
            'statuses' => OrderStatus::cases(),
            'tailors' => $this->tailors(),
        ]);
    }

    /**
     * Orders due today, tomorrow, overdue, and ready but not collected (ORD07).
     */
    public function due(Request $request): View
    {
        $base = fn () => StitchingOrder::query()->visibleTo($request->user())->with('customer', 'profile', 'tailor')->orderBy('promised_date')->orderBy('id');
        $inProgress = array_filter(OrderStatus::workflow(), fn (OrderStatus $status) => $status !== OrderStatus::Ready);

        return view('orders.due', [
            'sections' => [
                'Overdue' => $base()->whereIn('status', $inProgress)->whereDate('promised_date', '<', today())->get(),
                'Due today' => $base()->whereIn('status', $inProgress)->whereDate('promised_date', today())->get(),
                'Due tomorrow' => $base()->whereIn('status', $inProgress)->whereDate('promised_date', today()->addDay())->get(),
                'Ready, not collected' => $base()->where('status', OrderStatus::Ready)->get(),
            ],
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $customer = $request->query('customer') ? Customer::query()->find($request->query('customer')) : null;

        if (! $customer) {
            return view('orders.pick-customer');
        }

        $order = new StitchingOrder([
            'type' => $request->query('parent') ? OrderType::Alteration : OrderType::Stitching,
            'parent_order_id' => $request->query('parent'),
            'cloth_source' => $request->query('sale') ? ClothSource::Shop : ClothSource::Customer,
            'sale_id' => $request->query('sale'),
            'customer_profile_id' => $request->query('profile'),
            'pieces' => 1,
            'promised_date' => today()->addDays(7),
        ]);

        return view('orders.form', $this->formData($order, $customer, $request->integer('from')));
    }

    public function store(StitchingOrderRequest $request): RedirectResponse
    {
        $order = $this->orders->create($request->orderData(), $request->file('measurement_photo'));

        return redirect()->route('orders.show', $order)->with('success', __('Order :number created.', ['number' => $order->number]))->with('print_slip', true);
    }

    public function show(Request $request, StitchingOrder $order): View
    {
        $this->authorizeView($request, $order);

        $order->load([
            'customer', 'profile', 'measurement.values.field', 'sale', 'parent', 'alterations', 'extras',
            'statusLogs.user', 'assignments.tailor', 'payments.user', 'tailor', 'creator',
        ]);

        return view('orders.show', [
            'order' => $order,
            'statuses' => OrderStatus::workflow(),
            'methods' => PaymentMethod::cases(),
            'tailors' => $this->tailors(),
            'readyMessage' => $this->readyMessage($order),
        ]);
    }

    public function edit(Request $request, StitchingOrder $order): View|RedirectResponse
    {
        if (! $order->isEditable()) {
            return redirect()->route('orders.show', $order)->with('error', __('Delivered and cancelled orders cannot be edited.'));
        }

        $order->load('extras', 'customer');

        return view('orders.form', $this->formData($order, $order->customer, $request->integer('from')));
    }

    public function update(StitchingOrderRequest $request, StitchingOrder $order): RedirectResponse
    {
        $this->orders->update($order, $request->orderData(), $request->file('measurement_photo'));

        return redirect()->route('orders.show', $order)->with('success', __('Order updated.'));
    }

    /**
     * Status change by staff or the assigned tailor (ORD05).
     */
    public function updateStatus(Request $request, StitchingOrder $order): RedirectResponse
    {
        $this->authorizeView($request, $order);

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_map(fn (OrderStatus $status) => $status->value, OrderStatus::workflow()))],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $order = $this->orders->changeStatus($order, OrderStatus::from($validated['status']), $validated['notes'] ?? null);

        $redirect = redirect()->route('orders.show', $order)->with('success', __('Status changed to :label.', ['label' => $order->status->label()]));

        return $order->status === OrderStatus::Ready ? $redirect->with('notify_ready', true) : $redirect;
    }

    public function addPayment(Request $request, StitchingOrder $order): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference_no' => ['nullable', 'string', 'max:80'],
        ]);

        $this->orders->addPayment($order, (float) $validated['amount'], $validated['method'], $validated['reference_no'] ?? null);

        if ($payment = $order->payments()->latest('id')->first()) {
            $this->notifications->notifyPaymentReceived($payment, $order->customer);
        }

        return back()->with('success', __('Payment of :amount received.', ['amount' => number_format($validated['amount'], 2)]));
    }

    /**
     * Delivery: collect the remaining balance and mark Delivered (ORD09).
     */
    public function deliver(Request $request, StitchingOrder $order): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference_no' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $order = $this->orders->deliver($order, (float) ($validated['amount'] ?? 0), $validated['method'], $validated['reference_no'] ?? null, $validated['notes'] ?? null);

        return redirect()->route('orders.show', $order)->with('success', __('Order delivered.'));
    }

    public function cancel(Request $request, StitchingOrder $order): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:200'],
            'refund_method' => ['nullable', Rule::enum(PaymentMethod::class)],
        ]);

        $this->orders->cancel($order, $validated['reason'], $validated['refund_method'] ?? null);

        return redirect()->route('orders.show', $order)->with('success', __('Order cancelled.'));
    }

    /**
     * Customer copy with barcode (ORD04).
     */
    public function slip(Request $request, StitchingOrder $order): View
    {
        $this->authorizeView($request, $order);
        $order->load('customer', 'profile', 'extras');

        return view('orders.slip', ['order' => $order, 'shopName' => Setting::get('shop_name'), 'currency' => Setting::get('currency')]);
    }

    /**
     * Tailor copy with the measurement used for this order (MSR08).
     */
    public function measurementSlip(Request $request, StitchingOrder $order): View
    {
        $this->authorizeView($request, $order);
        $order->load('customer', 'profile', 'measurement.values.field', 'tailor');

        return view('measurements.slip', [
            'measurement' => $order->measurement,
            'order' => $order,
            'shopName' => Setting::get('shop_name'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(StitchingOrder $order, Customer $customer, int $fromMeasurementId): array
    {
        $customer->load('profiles.currentMeasurement.values');
        $profiles = $customer->profiles;
        $selectedProfileId = (int) old('customer_profile_id', $order->customer_profile_id ?? $profiles->first()?->id);

        $startingValues = $profiles->mapWithKeys(function ($profile) use ($order, $fromMeasurementId, $selectedProfileId): array {
            $source = $profile->currentMeasurement;

            if ($order->exists && $order->customer_profile_id === $profile->id && $order->measurement_id) {
                $source = Measurement::query()->with('values')->find($order->measurement_id);
            }

            if ($fromMeasurementId && $profile->id === $selectedProfileId) {
                $source = Measurement::query()->with('values')->where('customer_profile_id', $profile->id)->find($fromMeasurementId) ?? $source;
            }

            return [$profile->id => $source];
        });

        return [
            'order' => $order,
            'customer' => $customer,
            'profiles' => $profiles,
            'selectedProfileId' => $selectedProfileId,
            'startingValues' => $startingValues,
            'versions' => Measurement::query()->whereIn('customer_profile_id', $profiles->pluck('id'))->orderByDesc('version')->get()->groupBy('customer_profile_id'),
            'fields' => $profiles->mapWithKeys(fn ($profile) => [$profile->id => $this->measurements->fieldsFor($profile)]),
            'recentSales' => $customer->sales()->where('status', 'completed')->latest('id')->limit(15)->get(['id', 'number', 'sale_date', 'total']),
            'previousOrders' => $customer->orders()->where('status', OrderStatus::Delivered)->latest('id')->limit(20)->get(['id', 'number', 'order_date']),
            'types' => OrderType::cases(),
            'sources' => ClothSource::cases(),
            'methods' => PaymentMethod::cases(),
        ];
    }

    private function authorizeView(Request $request, StitchingOrder $order): void
    {
        $user = $request->user();

        abort_unless($user->can('orders.view') || ($user->can('orders.view_assigned') && $order->tailor_id === $user->id), 403);
    }

    /**
     * @return Collection<int, User>
     */
    private function tailors(): Collection
    {
        return User::query()->active()->whereHas('role', fn ($q) => $q->where('slug', 'tailor'))->orderBy('name')->get(['id', 'name']);
    }

    /**
     * WhatsApp click-to-chat link telling the customer the order is ready (ORD08).
     */
    private function readyMessage(StitchingOrder $order): ?string
    {
        if ($order->status !== OrderStatus::Ready) {
            return null;
        }

        $text = sprintf(
            "Assalam o Alaikum %s,\nYour order %s is ready for collection at %s.%s",
            $order->customer->name,
            $order->number,
            Setting::get('shop_name'),
            $order->balanceDue() > 0 ? "\nBalance due: ".Setting::get('currency').' '.number_format($order->balanceDue(), 0) : '',
        );

        return 'https://wa.me/'.$order->customer->whatsappNumber().'?text='.rawurlencode($text);
    }
}
