<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BarcodeLabelController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeveloperController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\MeasurementController;
use App\Http\Controllers\MeasurementFieldController;
use App\Http\Controllers\MessagingController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfitController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\ReceivableController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleReturnController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StitchingOrderController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TailorAssignmentController;
use App\Http\Controllers\TailorController;
use App\Http\Middleware\EnsureDeveloperSession;
use App\Http\Middleware\EnsureMessagingEnabled;
use App\Models\Setting;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

// Lets Chrome / Edge install the app with a desktop icon; it opens on the sale screen.
Route::get('manifest.webmanifest', function () {
    $name = (string) (Setting::get('shop_name') ?: config('app.name'));

    return response()->json([
        'name' => $name,
        'short_name' => Str::limit($name, 12, ''),
        'description' => __('Point of sale'),
        'id' => '/',
        'start_url' => '/sales/create',
        'scope' => '/',
        'display' => 'standalone',
        'background_color' => '#f1f5f9',
        'theme_color' => '#475569',
        'icons' => [
            ['src' => '/icons/app-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/icons/app-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/icons/app-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ],
    ], 200, ['Content-Type' => 'application/manifest+json']);
})->name('manifest');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:20,1');
});

// Developer login, outside the shop's users: resets the owner's password when it is forgotten.
Route::prefix('developer')->name('developer.')->group(function () {
    Route::get('login', [DeveloperController::class, 'create'])->name('login');
    Route::post('login', [DeveloperController::class, 'store'])->middleware('throttle:10,1');

    Route::middleware(EnsureDeveloperSession::class)->group(function () {
        Route::get('/', [DeveloperController::class, 'panel'])->name('panel');
        Route::put('owner', [DeveloperController::class, 'resetOwner'])->name('owner.update');
        Route::post('logout', [DeveloperController::class, 'destroy'])->name('logout');
    });
});

Route::post('locale/{locale}', [LocaleController::class, 'update'])->whereIn('locale', ['en', 'ur'])->name('locale.update');

// Receipt shared on WhatsApp: no login, but the link is signed and expires.
Route::get('receipts/{sale}', [SaleController::class, 'sharedReceipt'])->middleware('signed')->name('receipts.shared');

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('phone-check', [CustomerController::class, 'checkPhone'])->name('phone.check');
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::middleware('can:orders.assign')->group(function () {
        Route::get('tailors', [TailorController::class, 'index'])->name('tailors.index');
        Route::post('tailors', [TailorController::class, 'store'])->name('tailors.store');
        Route::put('tailors/{tailor}', [TailorController::class, 'update'])->name('tailors.update');
    });
    Route::middleware('can:users.manage')->group(function () {
        Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
        Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
        Route::put('staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
    });
    Route::middleware('can:settings.manage')->group(function () {
        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    });

    // 3.2 Product and stock management
    Route::prefix('masters/{type}')->name('masters.')->whereIn('type', array_keys(MasterDataController::TYPES))->group(function () {
        Route::get('/', [MasterDataController::class, 'index'])->middleware('can:masters.manage')->name('index');
        Route::post('/', [MasterDataController::class, 'store'])->middleware('can:masters.manage')->name('store');
        Route::put('{id}', [MasterDataController::class, 'update'])->middleware('can:masters.manage')->name('update');
        Route::delete('{id}', [MasterDataController::class, 'destroy'])->middleware('can:records.delete')->name('destroy');
    });

    Route::middleware('can:products.import')->group(function () {
        Route::get('products/import', [ProductImportController::class, 'create'])->name('products.import');
        Route::post('products/import', [ProductImportController::class, 'store'])->name('products.import.store');
        Route::get('products/import/template', [ProductImportController::class, 'template'])->name('products.import.template');
    });
    Route::middleware('can:products.manage')->group(function () {
        Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::get('labels', [LabelController::class, 'create'])->name('labels.create');
        Route::post('labels/print', [LabelController::class, 'print'])->name('labels.print');

        // Barcode generator: price labels with their own selling price.
        Route::get('barcodes', [BarcodeLabelController::class, 'index'])->name('barcode-labels.index');
        Route::post('barcodes', [BarcodeLabelController::class, 'store'])->name('barcode-labels.store');
        Route::get('barcodes/{barcodeLabel}/print', [BarcodeLabelController::class, 'print'])->name('barcode-labels.print');
        Route::post('barcodes/{barcodeLabel}/reprint', [BarcodeLabelController::class, 'reprint'])->name('barcode-labels.reprint');
        Route::patch('barcodes/{barcodeLabel}/toggle', [BarcodeLabelController::class, 'toggle'])->name('barcode-labels.toggle');
    });
    Route::delete('products/{product}', [ProductController::class, 'destroy'])->middleware('can:records.delete')->name('products.destroy');
    Route::middleware('can:products.view')->group(function () {
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::get('products/lookup', [ProductController::class, 'lookup'])->name('products.lookup');
        Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
        Route::get('stock/low', [StockController::class, 'lowStock'])->name('stock.low');
        Route::get('stock/ledger/{variant}', [StockController::class, 'ledger'])->name('stock.ledger');
    });

    // 3.3 Supplier management
    Route::middleware('can:suppliers.manage')->group(function () {
        Route::get('suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create');
        Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::get('suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit');
        Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
    });
    Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->middleware('can:records.delete')->name('suppliers.destroy');
    Route::middleware('can:suppliers.pay')->group(function () {
        Route::get('suppliers/{supplier}/payments/create', [SupplierController::class, 'createPayment'])->name('suppliers.payments.create');
        Route::post('suppliers/{supplier}/payments', [SupplierController::class, 'storePayment'])->name('suppliers.payments.store');
        Route::patch('suppliers/{supplier}/payments/{payment}/cancel', [SupplierController::class, 'cancelPayment'])->name('suppliers.payments.cancel');
    });
    Route::middleware('can:suppliers.view')->group(function () {
        Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::get('suppliers/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');
        Route::get('suppliers/{supplier}/statement', [SupplierController::class, 'statement'])->name('suppliers.statement');
    });

    // 3.4 Purchase management
    Route::middleware('can:purchases.manage')->group(function () {
        Route::get('purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
        Route::post('purchases', [PurchaseController::class, 'store'])->name('purchases.store');
        Route::get('purchases/{purchase}/edit', [PurchaseController::class, 'edit'])->name('purchases.edit');
        Route::put('purchases/{purchase}', [PurchaseController::class, 'update'])->name('purchases.update');
    });
    Route::patch('purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])->middleware('can:purchases.cancel')->name('purchases.cancel');
    Route::middleware('can:purchases.return')->group(function () {
        Route::get('purchases/{purchase}/returns/create', [PurchaseReturnController::class, 'create'])->name('purchase-returns.create');
        Route::post('purchases/{purchase}/returns', [PurchaseReturnController::class, 'store'])->name('purchase-returns.store');
    });
    Route::middleware('can:purchases.view')->group(function () {
        Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
        Route::get('purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');
        Route::get('purchases/{purchase}/voucher', [PurchaseController::class, 'voucher'])->name('purchases.voucher');
        Route::get('purchase-returns/{purchaseReturn}', [PurchaseReturnController::class, 'show'])->name('purchase-returns.show');
    });

    // 3.5 Customer management
    Route::middleware('can:customers.manage')->group(function () {
        Route::get('customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::post('customers/{customer}/profiles', [CustomerProfileController::class, 'store'])->name('customers.profiles.store');
        Route::put('customers/{customer}/profiles/{profile}', [CustomerProfileController::class, 'update'])->name('customers.profiles.update');
        Route::delete('customers/{customer}/profiles/{profile}', [CustomerProfileController::class, 'destroy'])->name('customers.profiles.destroy');
    });
    Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->middleware('can:records.delete')->name('customers.destroy');
    Route::middleware('can:payments.receive')->group(function () {
        Route::get('customers/{customer}/payments/create', [CustomerController::class, 'createPayment'])->name('customers.payments.create');
        Route::post('customers/{customer}/payments', [CustomerController::class, 'storePayment'])->name('customers.payments.store');
    });
    Route::middleware('can:customers.view')->group(function () {
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/lookup', [CustomerController::class, 'lookup'])->name('customers.lookup');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::get('measurements/{measurement}', [MeasurementController::class, 'show'])->name('measurements.show');
        Route::get('measurements/{measurement}/print', [MeasurementController::class, 'print'])->name('measurements.print');
    });

    // 3.6 Measurement management
    Route::middleware('can:measurements.manage')->group(function () {
        Route::get('profiles/{profile}/measurements/create', [MeasurementController::class, 'create'])->name('measurements.create');
        Route::post('profiles/{profile}/measurements', [MeasurementController::class, 'store'])->name('measurements.store');
    });
    Route::middleware('can:measurements.configure')->group(function () {
        Route::get('measurement-fields/{template}', [MeasurementFieldController::class, 'index'])->name('measurement-fields.index');
        Route::post('measurement-fields/{template}', [MeasurementFieldController::class, 'store'])->name('measurement-fields.store');
        Route::put('measurement-fields/field/{field}', [MeasurementFieldController::class, 'update'])->name('measurement-fields.update');
    });

    // 3.7 Sales management
    Route::middleware('can:sales.create')->group(function () {
        Route::get('sales/create', [SaleController::class, 'create'])->name('sales.create');
        Route::post('sales', [SaleController::class, 'store'])->name('sales.store');
        Route::get('sales/offline/catalogue', [SaleController::class, 'offlineCatalogue'])->name('sales.offline.catalogue');
        Route::get('sales/offline/token', [SaleController::class, 'offlineToken'])->name('sales.offline.token');
        Route::delete('sales/{sale}/discard', [SaleController::class, 'discard'])->name('sales.discard');
    });
    Route::middleware('can:sales.return')->group(function () {
        Route::get('sales/{sale}/returns/create', [SaleReturnController::class, 'create'])->name('sale-returns.create');
        Route::post('sales/{sale}/returns', [SaleReturnController::class, 'store'])->name('sale-returns.store');
    });
    Route::middleware('can:sales.view')->group(function () {
        Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
        Route::get('sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
        Route::get('sales/{sale}/receipt', [SaleController::class, 'receipt'])->name('sales.receipt');
        Route::get('sale-returns/{saleReturn}', [SaleReturnController::class, 'show'])->name('sale-returns.show');
    });

    // 3.8 Stitching order management. Tailors reach index, show and status through orders.view_assigned.
    Route::middleware('can:orders.manage')->group(function () {
        Route::get('orders/create', [StitchingOrderController::class, 'create'])->name('orders.create');
        Route::post('orders', [StitchingOrderController::class, 'store'])->name('orders.store');
        Route::get('orders/{order}/edit', [StitchingOrderController::class, 'edit'])->name('orders.edit');
        Route::put('orders/{order}', [StitchingOrderController::class, 'update'])->name('orders.update');
        Route::post('orders/{order}/deliver', [StitchingOrderController::class, 'deliver'])->name('orders.deliver');
        Route::patch('orders/{order}/cancel', [StitchingOrderController::class, 'cancel'])->name('orders.cancel');
    });
    Route::post('orders/{order}/payments', [StitchingOrderController::class, 'addPayment'])->middleware('can:payments.receive')->name('orders.payments.store');
    Route::middleware('can:orders.assign')->group(function () {
        Route::post('orders/{order}/assignments', [TailorAssignmentController::class, 'store'])->name('orders.assignments.store');
        Route::patch('assignments/{assignment}/receive', [TailorAssignmentController::class, 'receive'])->name('assignments.receive');
    });
    Route::patch('orders/{order}/status', [StitchingOrderController::class, 'updateStatus'])->middleware('can:orders.update_status')->name('orders.status');
    Route::middleware('can:orders.view-any')->group(function () {
        Route::get('orders/due', [StitchingOrderController::class, 'due'])->name('orders.due');
        Route::get('orders', [StitchingOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [StitchingOrderController::class, 'show'])->name('orders.show');
        Route::get('orders/{order}/slip', [StitchingOrderController::class, 'slip'])->name('orders.slip');
        Route::get('orders/{order}/measurement-slip', [StitchingOrderController::class, 'measurementSlip'])->name('orders.measurement-slip');
        Route::get('orders/{order}/suit-tag', [StitchingOrderController::class, 'suitTag'])->name('orders.suit-tag');
    });

    // 3.9 Payments and ledger
    Route::middleware('can:receivables.view')->group(function () {
        Route::get('receivables', [ReceivableController::class, 'index'])->name('receivables.index');
        Route::get('customers/{customer}/statement', [CustomerController::class, 'statement'])->name('customers.statement');
    });
    Route::patch('customers/{customer}/payments/{payment}/cancel', [CustomerController::class, 'cancelPayment'])->middleware('can:payments.receive')->name('customers.payments.cancel');
    Route::get('profit', [ProfitController::class, 'index'])->middleware('can:cost.view')->name('profit.index');

    // 3.10 Notifications and communication
    Route::middleware(['can:messages.send', EnsureMessagingEnabled::class])->group(function () {
        Route::post('receivables/remind', [ReceivableController::class, 'remind'])->name('receivables.remind');
        Route::get('messages', [MessagingController::class, 'log'])->name('notifications.index');
        Route::post('messages/{log}/resend', [MessagingController::class, 'resend'])->name('notifications.resend');
    });
    Route::middleware(['can:settings.manage', EnsureMessagingEnabled::class])->group(function () {
        Route::get('settings/messaging', [MessagingController::class, 'settings'])->name('messaging.settings');
        Route::put('settings/messaging', [MessagingController::class, 'updateSettings'])->name('messaging.settings.update');
        Route::put('settings/messaging/templates', [MessagingController::class, 'updateTemplates'])->name('messaging.templates.update');
        Route::post('settings/messaging/test', [MessagingController::class, 'test'])->name('messaging.test');
    });
});
