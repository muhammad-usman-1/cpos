<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Colour;
use App\Models\Customer;
use App\Models\CustomerProfile;
use App\Models\DayClosing;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Fabric;
use App\Models\MeasurementField;
use App\Models\MessageTemplate;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Setting;
use App\Models\StitchingOrder;
use App\Models\StockTake;
use App\Models\Supplier;
use App\Models\TailorAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'user' => User::class,
            'role' => Role::class,
            'category' => Category::class,
            'brand' => Brand::class,
            'fabric' => Fabric::class,
            'colour' => Colour::class,
            'product' => Product::class,
            'product_variant' => ProductVariant::class,
            'stock_take' => StockTake::class,
            'supplier' => Supplier::class,
            'payment' => Payment::class,
            'purchase' => Purchase::class,
            'purchase_return' => PurchaseReturn::class,
            'customer' => Customer::class,
            'customer_profile' => CustomerProfile::class,
            'measurement_field' => MeasurementField::class,
            'sale' => Sale::class,
            'sale_return' => SaleReturn::class,
            'day_closing' => DayClosing::class,
            'stitching_order' => StitchingOrder::class,
            'tailor_assignment' => TailorAssignment::class,
            'expense' => Expense::class,
            'expense_category' => ExpenseCategory::class,
            'message_template' => MessageTemplate::class,
        ]);

        Gate::before(fn (User $user): ?bool => $user->isOwner() ? true : null);

        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user): bool => $user->hasPermission($permission));
        }

        Gate::define('orders.view-any', fn (User $user): bool => $user->hasPermission(Permission::ViewOrders) || $user->hasPermission(Permission::ViewAssignedOrders));

        Blade::if('barcodes', fn (): bool => Setting::usesBarcodes());
        Blade::if('messaging', fn (): bool => Setting::usesMessaging());

        View::composer('components.layouts.app', function ($view): void {
            $view->with('shopName', Setting::get('shop_name'))
                ->with('idleTimeoutMinutes', Setting::idleTimeoutMinutes());
        });
    }
}
