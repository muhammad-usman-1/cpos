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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->nullable()->unique();
            $table->string('design_code', 60)->index();
            $table->string('name')->index();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fabric_id')->nullable()->constrained()->nullOnDelete();
            $table->string('season', 30)->nullable();
            $table->string('piece_type', 30)->nullable();
            $table->string('unit', 20)->default('suit');
            $table->decimal('fabric_length', 8, 2)->nullable();
            $table->string('length_unit', 10)->default('meter');
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('average_cost', 12, 2)->default(0);
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->decimal('min_sale_price', 12, 2)->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('colour_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku', 40)->nullable()->unique();
            $table->decimal('stock_qty', 12, 3)->default(0);
            $table->decimal('min_stock', 12, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30)->index();
            $table->decimal('qty_in', 12, 3)->default(0);
            $table->decimal('qty_out', 12, 3)->default(0);
            $table->decimal('balance_after', 12, 3);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->string('reason', 30)->nullable();
            $table->string('notes')->nullable();
            $table->nullableMorphs('reference');
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('moved_at')->useCurrent()->index();
            $table->timestamps();
        });

        Schema::create('stock_takes', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->nullable()->unique();
            $table->string('status', 20)->default('draft');
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_take_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_take_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->decimal('system_qty', 12, 3);
            $table->decimal('counted_qty', 12, 3)->nullable();
            $table->decimal('difference', 12, 3)->nullable();
            $table->timestamps();
            $table->unique(['stock_take_id', 'product_variant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_take_items');
        Schema::dropIfExists('stock_takes');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('product_photos');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
    }
};
