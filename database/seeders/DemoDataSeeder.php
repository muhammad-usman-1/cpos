<?php

namespace Database\Seeders;

use App\Enums\ClothSource;
use App\Enums\MeasurementTemplate;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\RefundMethod;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Colour;
use App\Models\Customer;
use App\Models\CustomerProfile;
use App\Models\Fabric;
use App\Models\MeasurementField;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Sale;
use App\Models\StitchingOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\MeasurementService;
use App\Services\PaymentService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use App\Services\StitchingOrderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Fake but consistent shop data for demos and manual testing:
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * Everything goes through the real services, so stock, cost, ledgers and balances add up.
 * Dates are spread over the last two months. Staff logins use the password "password".
 */
class DemoDataSeeder extends Seeder
{
    /**
     * Typical body sizes in inches: [min, max] for an adult. Kids use 60 percent.
     *
     * @var array<string, array{0: float, 1: float}>
     */
    private const SIZES = [
        'length' => [38, 44], 'shoulder' => [16.5, 19.5], 'chest' => [36, 46], 'waist' => [30, 42], 'hip' => [36, 46],
        'sleeve_length' => [22, 25], 'sleeve_opening' => [6, 8], 'armhole' => [17, 21], 'collar' => [14.5, 17],
        'neck_depth_front' => [6, 9], 'neck_depth_back' => [4, 7], 'daman_width' => [21, 26], 'trouser_length' => [37, 42],
        'pancha_width' => [7, 9.5], 'thigh' => [21, 28], 'knee' => [15, 20], 'bottom' => [6.5, 9],
    ];

    public function __construct(
        private PurchaseService $purchases,
        private PaymentService $payments,
        private SaleService $sales,
        private MeasurementService $measurements,
        private StitchingOrderService $orders,
    ) {}

    public function run(): void
    {
        $this->call([RolesAndPermissionsSeeder::class, MeasurementFieldSeeder::class, MessageTemplateSeeder::class]);

        $owner = User::query()->where('username', 'owner')->firstOrFail();
        Auth::setUser($owner);

        $staff = $this->staff();
        $masters = $this->masters();
        $variants = $this->products($masters);
        $suppliers = Supplier::factory()->count(6)->create();

        $this->purchaseStock($variants, $suppliers, $staff);
        $customers = $this->customers();
        $this->sell($variants, $customers, $staff);
        $this->stitchingOrders($customers, $staff);

        Carbon::setTestNow();
        Auth::setUser($owner);

        $this->command?->info(sprintf(
            'Demo data ready: %d products, %d suppliers, %d customers, %d sales, %d orders.',
            Product::count(), Supplier::count(), Customer::count(), Sale::where('status', 'completed')->count(), StitchingOrder::count(),
        ));
    }

    /**
     * @return array{manager: User, salesman: User, tailors: Collection<int, User>}
     */
    private function staff(): array
    {
        // Only the owner logs in. Old staff are kept inactive as the names on past bills; tailors stay active as workers who take orders.
        $role = fn (string $slug) => Role::query()->where('slug', $slug)->value('id');
        $make = fn (string $username, string $name, string $slug, string $phone) => User::query()->firstOrCreate(
            ['username' => $username],
            ['name' => $name, 'phone' => $phone, 'password' => 'password', 'role_id' => $role($slug), 'is_active' => $slug === 'tailor'],
        );

        return [
            'manager' => $make('manager', 'Imran Manager', 'manager', '03001110001'),
            'salesman' => $make('salesman', 'Bilal Salesman', 'salesman', '03001110002'),
            'tailors' => collect([
                $make('tailor1', 'Master Rafiq', 'tailor', '03001110003'),
                $make('tailor2', 'Ustad Nadeem', 'tailor', '03001110004'),
            ]),
        ];
    }

    /**
     * @return array{categories: Collection<int, Category>, brands: Collection<int, Brand>, fabrics: Collection<int, Fabric>, colours: Collection<int, Colour>}
     */
    private function masters(): array
    {
        $make = fn (string $model, array $names) => collect($names)->map(fn ($name) => $model::query()->withTrashed()->firstOrCreate(['name' => $name], ['is_active' => true]));

        return [
            'categories' => $make(Category::class, ['Unstitched', 'Loose fabric', 'Formal', 'Kids']),
            'brands' => $make(Brand::class, ['Gul Ahmed', 'Khaadi', 'Alkaram', 'Sapphire', 'Nishat Linen', 'Local']),
            'fabrics' => $make(Fabric::class, ['Lawn', 'Cotton', 'Khaddar', 'Linen', 'Chiffon', 'Wash and wear']),
            'colours' => $make(Colour::class, ['White', 'Black', 'Navy Blue', 'Sky Blue', 'Maroon', 'Pink', 'Mustard', 'Sea Green', 'Grey', 'Off White']),
        ];
    }

    /**
     * @param  array{categories: Collection<int, Category>, brands: Collection<int, Brand>, fabrics: Collection<int, Fabric>, colours: Collection<int, Colour>}  $masters
     * @return Collection<int, ProductVariant>
     */
    private function products(array $masters): Collection
    {
        $products = collect();

        foreach (range(1, 24) as $i) {
            $products->push(Product::factory()->create([
                'category_id' => $masters['categories']->whereIn('name', ['Unstitched', 'Formal'])->random()->id,
                'brand_id' => $masters['brands']->random()->id,
                'fabric_id' => $masters['fabrics']->random()->id,
            ]));
        }

        foreach (range(1, 4) as $i) {
            $products->push(Product::factory()->looseFabric()->create([
                'category_id' => $masters['categories']->firstWhere('name', 'Loose fabric')->id,
                'brand_id' => $masters['brands']->firstWhere('name', 'Local')->id,
                'fabric_id' => $masters['fabrics']->whereIn('name', ['Cotton', 'Wash and wear'])->random()->id,
            ]));
        }

        return $products->flatMap(function (Product $product) use ($masters): Collection {
            return $masters['colours']->random(fake()->numberBetween(1, 3))->map(fn (Colour $colour) => ProductVariant::factory()->create([
                'product_id' => $product->id,
                'colour_id' => $colour->id,
                'min_stock' => $product->unit->isLooseFabric() ? 20 : fake()->randomElement([2, 3, 5]),
            ]));
        })->each->load('product');
    }

    /**
     * @param  Collection<int, ProductVariant>  $variants
     * @param  Collection<int, Supplier>  $suppliers
     * @param  array{manager: User, salesman: User, tailors: Collection<int, User>}  $staff
     */
    private function purchaseStock(Collection $variants, Collection $suppliers, array $staff): void
    {
        Auth::setUser($staff['manager']);

        // Every variant is bought once in the first weeks, then a few restocks later.
        $batches = $variants->shuffle()->chunk(6)->values();

        foreach ($batches as $index => $batch) {
            $this->at(now()->subDays(60 - $index * 2), function () use ($batch, $suppliers): void {
                $lines = $batch->map(fn (ProductVariant $variant) => [
                    'product_variant_id' => $variant->id,
                    'quantity' => $variant->product->unit->isLooseFabric() ? fake()->numberBetween(60, 150) : fake()->numberBetween(6, 18),
                    'rate' => (float) $variant->product->cost_price * fake()->randomFloat(2, 0.95, 1.05),
                ])->values()->all();

                $total = collect($lines)->sum(fn ($line) => $line['quantity'] * $line['rate']);

                $this->purchases->create([
                    'supplier_id' => $suppliers->random()->id,
                    'purchase_date' => today()->toDateString(),
                    'invoice_no' => (string) fake()->numberBetween(1000, 9999),
                    'discount' => fake()->boolean(30) ? round($total * 0.02, -1) : 0,
                    'other_charges' => fake()->boolean(40) ? fake()->randomElement([300, 500, 800]) : 0,
                    'paid_amount' => fake()->randomElement([0, round($total * 0.5, -2), round($total * 0.3, -2)]),
                    'payment_method' => fake()->randomElement(['cash', 'bank']),
                    'items' => $lines,
                ]);
            });
        }

        foreach ($suppliers as $supplier) {
            $supplier->refresh();

            if ((float) $supplier->balance > 1000) {
                $this->at(now()->subDays(fake()->numberBetween(10, 30)), fn () => $this->payments->paySupplier($supplier, [
                    'payment_date' => today()->toDateString(),
                    'amount' => round((float) $supplier->balance * 0.4, -2),
                    'method' => fake()->randomElement(['cash', 'bank', 'cheque']),
                    'reference_no' => (string) fake()->numberBetween(100000, 999999),
                ]));
            }
        }
    }

    /**
     * @return Collection<int, Customer>
     */
    private function customers(): Collection
    {
        $customers = Customer::factory()->count(40)->create();

        foreach ($customers as $customer) {
            $self = $customer->profiles()->create(['name' => 'Self', 'template' => fake()->boolean(70) ? MeasurementTemplate::Gents : MeasurementTemplate::Ladies]);
            $profiles = collect([$self]);

            foreach (range(1, fake()->numberBetween(0, 2)) as $ignored) {
                $profiles->push(CustomerProfile::factory()->family()->create(['customer_id' => $customer->id]));
            }

            foreach ($profiles as $profile) {
                if (fake()->boolean(85)) {
                    $this->at(now()->subDays(fake()->numberBetween(40, 90)), fn () => $this->measurements->save($profile, $this->fakeMeasurement($profile), 'in', fake()->optional(0.2)->randomElement(['Keep fitting loose', 'Left sleeve 0.5 longer', 'No pocket on shirt'])));

                    // Some customers were re-measured later, which creates version 2.
                    if (fake()->boolean(25)) {
                        $this->at(now()->subDays(fake()->numberBetween(5, 30)), fn () => $this->measurements->save($profile, $this->fakeMeasurement($profile), 'in', 'Re-measured'));
                    }
                }
            }
        }

        return $customers->each->load('profiles');
    }

    /**
     * @param  Collection<int, ProductVariant>  $variants
     * @param  Collection<int, Customer>  $customers
     * @param  array{manager: User, salesman: User, tailors: Collection<int, User>}  $staff
     */
    private function sell(Collection $variants, Collection $customers, array $staff): void
    {
        foreach (range(1, 45) as $i) {
            Auth::setUser(fake()->boolean(70) ? $staff['salesman'] : $staff['manager']);

            $this->at(now()->subDays(30 - intdiv($i * 30, 45))->setTime(fake()->numberBetween(11, 21), fake()->numberBetween(0, 59)), function () use ($variants, $customers): void {
                $customer = fake()->boolean(65) ? $customers->random() : null;
                $inStock = ProductVariant::query()->with('product')->whereIn('id', $variants->pluck('id'))->where('stock_qty', '>', 4)->get();
                $items = $inStock->random(min($inStock->count(), fake()->numberBetween(1, 3)))->map(fn (ProductVariant $variant) => [
                    'product_variant_id' => $variant->id,
                    'quantity' => $variant->product->unit->isLooseFabric() ? fake()->randomElement([2.5, 3, 4.5]) : 1,
                    'price' => (float) $variant->product->sale_price,
                    'discount' => fake()->boolean(20) ? 100 : 0,
                ])->values()->all();

                $total = collect($items)->sum(fn ($item) => $item['quantity'] * $item['price'] - $item['discount']);
                $billDiscount = $customer && (float) $customer->discount_percent > 0 ? round($total * (float) $customer->discount_percent / 100, -1) : 0;
                $due = $total - $billDiscount;

                // Mostly cash, some split card + cash, some customers leave part on credit.
                $payments = match (true) {
                    $customer !== null && fake()->boolean(25) => [['method' => 'cash', 'amount' => round($due * 0.5, -2)]],
                    fake()->boolean(20) => [['method' => 'card', 'amount' => round($due * 0.6, -2)], ['method' => 'cash', 'amount' => $due - round($due * 0.6, -2)]],
                    default => [['method' => fake()->randomElement(['cash', 'cash', 'cash', 'wallet']), 'amount' => $due]],
                };

                $sale = $this->sales->complete([
                    'customer_id' => $customer?->id,
                    'bill_discount' => $billDiscount,
                    'extra_charges' => 0,
                    'items' => $items,
                    'payments' => $payments,
                ]);

                if (fake()->boolean(8)) {
                    $item = $sale->items()->first();
                    $this->sales->recordReturn($sale, [
                        'return_date' => today()->toDateString(),
                        'refund_method' => $customer ? RefundMethod::CustomerBalance->value : RefundMethod::Cash->value,
                        'notes' => 'Colour not liked',
                        'items' => [['sale_item_id' => $item->id, 'quantity' => min(1, (float) $item->quantity)]],
                    ]);
                }
            });
        }

        // Some customers paid off part of their credit later.
        foreach (Customer::query()->where('balance', '>', 0)->get() as $customer) {
            if (fake()->boolean(50)) {
                $this->payments->receive($customer, [
                    'payment_date' => today()->toDateString(),
                    'amount' => round((float) $customer->balance * 0.5, -1),
                    'method' => 'cash',
                    'notes' => 'Part payment',
                ]);
            }
        }
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @param  array{manager: User, salesman: User, tailors: Collection<int, User>}  $staff
     */
    private function stitchingOrders(Collection $customers, array $staff): void
    {
        $measured = $customers->filter(fn (Customer $customer) => $customer->profiles->contains(fn ($profile) => $profile->currentMeasurement()->exists()));

        foreach (range(1, 28) as $i) {
            Auth::setUser($staff['salesman']);
            $daysAgo = 28 - $i;

            $this->at(now()->subDays($daysAgo)->setTime(fake()->numberBetween(11, 20), 0), function () use ($measured, $staff, $daysAgo): void {
                $customer = $measured->random();
                $profile = $customer->profiles->first(fn ($profile) => $profile->currentMeasurement()->exists());
                $shopSale = $customer->sales()->where('status', 'completed')->latest('id')->first();
                $fromShop = $shopSale && fake()->boolean(40);
                $charges = $profile->template === MeasurementTemplate::Ladies ? fake()->randomElement([1800, 2200, 2800]) : fake()->randomElement([1200, 1500, 1800]);
                $extras = fake()->boolean(40) ? [['description' => fake()->randomElement(['Lining', 'Embroidery on collar', 'Piping', 'Extra pocket']), 'amount' => fake()->randomElement([200, 300, 500])]] : [];

                $order = $this->orders->create([
                    'customer_id' => $customer->id,
                    'customer_profile_id' => $profile->id,
                    'type' => OrderType::Stitching->value,
                    'cloth_source' => $fromShop ? ClothSource::Shop->value : ClothSource::Customer->value,
                    'sale_id' => $fromShop ? $shopSale->id : null,
                    'cloth_description' => $fromShop ? null : fake()->randomElement(['White wash and wear, 4.5m', 'Black khaddar suit', 'Printed lawn 3pc', 'Sky blue cotton']),
                    'pieces' => fake()->randomElement([1, 1, 2, 3]),
                    'design_notes' => fake()->randomElement(['Simple kurta, round daman', 'Sherwani collar, 2 side pockets', 'Shalwar with belt', 'Straight shirt, trouser']),
                    'stitching_charges' => $charges,
                    'extras' => $extras,
                    'promised_date' => today()->addDays(fake()->numberBetween(5, 10))->toDateString(),
                    'measurement_unit' => 'in',
                    'advance_amount' => fake()->randomElement([0, 500, 1000]),
                    'advance_method' => 'cash',
                ]);

                if ($daysAgo < 2) {
                    return;
                }

                Auth::setUser($staff['manager']);
                $tailor = $staff['tailors']->random();
                $this->orders->assign($order, $tailor, $order->pieces, fake()->randomElement([300, 400, 500]), null);

                // Older orders have moved further along; a few are deliberately left overdue.
                $steps = match (true) {
                    $daysAgo > 14 => fake()->boolean(85) ? 6 : 3,
                    $daysAgo > 8 => fake()->numberBetween(3, 5),
                    default => fake()->numberBetween(1, 3),
                };

                $workflow = [OrderStatus::Cutting, OrderStatus::Stitching, OrderStatus::Finishing, OrderStatus::Ready];

                foreach (array_slice($workflow, 0, min($steps, 4)) as $status) {
                    $this->orders->changeStatus($order, $status);
                }

                if ($steps >= 5) {
                    $this->orders->receivePieces($order->assignments()->first(), $order->pieces);
                }

                if ($steps >= 6) {
                    $order->refresh();
                    $this->orders->deliver($order, $order->balanceDue(), 'cash', null, null);
                }
            });
        }

        // One alteration on a delivered order, and one cancellation.
        $delivered = StitchingOrder::query()->where('status', OrderStatus::Delivered)->first();

        if ($delivered) {
            Carbon::setTestNow();
            $this->orders->create([
                'customer_id' => $delivered->customer_id,
                'customer_profile_id' => $delivered->customer_profile_id,
                'type' => OrderType::Alteration->value,
                'parent_order_id' => $delivered->id,
                'cloth_source' => ClothSource::Customer->value,
                'cloth_description' => 'Stitched suit from '.$delivered->number,
                'pieces' => 1,
                'design_notes' => 'Shorten sleeves by 1 inch',
                'stitching_charges' => 200,
                'promised_date' => today()->addDays(2)->toDateString(),
                'measurement_unit' => 'in',
            ]);
        }

        $open = StitchingOrder::query()->where('status', OrderStatus::Received)->orderByDesc('paid_amount')->first();

        if ($open) {
            $this->orders->cancel($open, 'Customer changed mind', 'cash');
        }
    }

    /**
     * @return array<int, string>
     */
    private function fakeMeasurement(CustomerProfile $profile): array
    {
        $scale = $profile->template === MeasurementTemplate::Kids ? 0.6 : 1;

        return MeasurementField::query()->forTemplate($profile->template)->get()->mapWithKeys(function (MeasurementField $field) use ($scale): array {
            if ($field->type === MeasurementField::TYPE_SELECT) {
                return [$field->id => fake()->randomElement($field->options ?? [''])];
            }

            [$min, $max] = self::SIZES[$field->key] ?? [10, 20];

            return [$field->id => (string) (round(fake()->randomFloat(2, $min, $max) * $scale * 4) / 4)];
        })->all();
    }

    /**
     * Run a step as if it happened at the given time, so sale, purchase and order dates are spread out.
     * Steps that break a business rule (for example not enough stock) are skipped, as the real screen would refuse them.
     */
    private function at(Carbon $time, callable $step): void
    {
        Carbon::setTestNow($time);

        try {
            $step();
        } catch (ValidationException $exception) {
            $this->command?->warn('Skipped: '.collect($exception->errors())->flatten()->first());
        } finally {
            Carbon::setTestNow();
        }
    }
}
