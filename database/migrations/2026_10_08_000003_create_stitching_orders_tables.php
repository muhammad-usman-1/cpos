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
        Schema::create('stitching_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->nullable()->unique();
            $table->string('type', 20)->default('stitching');
            $table->foreignId('parent_order_id')->nullable()->constrained('stitching_orders')->nullOnDelete();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('customer_profile_id')->constrained();
            $table->foreignId('measurement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cloth_source', 20)->default('customer');
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cloth_description')->nullable();
            $table->decimal('cloth_quantity', 8, 2)->nullable();
            $table->unsignedSmallInteger('pieces')->default(1);
            $table->text('design_notes')->nullable();
            $table->decimal('stitching_charges', 12, 2)->default(0);
            $table->decimal('extras_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->string('status', 20)->default('received')->index();
            $table->date('order_date');
            $table->date('promised_date')->index();
            $table->foreignId('tailor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('stitching_order_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stitching_order_id')->constrained()->cascadeOnDelete();
            $table->string('description', 100);
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });

        Schema::create('order_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stitching_order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('tailor_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stitching_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tailor_id')->constrained('users');
            $table->unsignedSmallInteger('pieces_given');
            $table->timestamp('given_at')->useCurrent();
            $table->unsignedSmallInteger('pieces_received')->default(0);
            $table->timestamp('received_at')->nullable();
            $table->decimal('rate_per_piece', 10, 2)->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tailor_assignments');
        Schema::dropIfExists('order_status_logs');
        Schema::dropIfExists('stitching_order_extras');
        Schema::dropIfExists('stitching_orders');
    }
};
