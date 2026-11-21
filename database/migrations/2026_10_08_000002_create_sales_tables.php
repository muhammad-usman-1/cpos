<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->nullable()->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('completed')->index();
            $table->date('sale_date')->index();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('item_discount', 14, 2)->default(0);
            $table->decimal('bill_discount', 14, 2)->default(0);
            $table->decimal('extra_charges', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->decimal('credit_amount', 14, 2)->default(0);
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained();
            $table->decimal('quantity', 12, 3);
            $table->decimal('price', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('amount', 14, 2);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('returned_qty', 12, 3)->default(0);
            $table->timestamps();
        });

        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->nullable()->unique();
            $table->foreignId('sale_id')->constrained();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->date('return_date')->index();
            $table->decimal('total', 14, 2)->default(0);
            $table->string('refund_method', 20);
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained();
            $table->foreignId('product_variant_id')->constrained();
            $table->decimal('quantity', 12, 3);
            $table->decimal('price', 12, 2);
            $table->decimal('amount', 14, 2);
            $table->timestamps();
        });

        Schema::create('day_closings', function (Blueprint $table) {
            $table->id();
            $table->date('closing_date')->unique();
            $table->decimal('opening_cash', 14, 2)->default(0);
            $table->decimal('cash_in', 14, 2)->default(0);
            $table->decimal('cash_out', 14, 2)->default(0);
            $table->decimal('expected_cash', 14, 2)->default(0);
            $table->decimal('counted_cash', 14, 2)->default(0);
            $table->decimal('difference', 14, 2)->default(0);
            $table->string('notes')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Walk-in sales take payments without a customer, so the payer becomes optional.
        Schema::table('payments', function (Blueprint $table) {
            $table->string('party_type')->nullable()->change();
            $table->unsignedBigInteger('party_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('day_closings');
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
