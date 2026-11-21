<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Price labels: a printed barcode tied to one product with its own selling price.
     */
    public function up(): void
    {
        Schema::create('barcode_labels', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->nullable()->unique();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->decimal('price', 12, 2);
            $table->unsignedInteger('copies_printed')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_variant_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barcode_labels');
    }
};
