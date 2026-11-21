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
        // Which credit invoices and stitching orders a customer payment settles (PAY02).
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->morphs('allocatable');
            $table->decimal('amount', 14, 2);
            $table->timestamps();
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->nullable()->unique();
            $table->foreignId('expense_category_id')->constrained();
            $table->date('expense_date')->index();
            $table->decimal('amount', 14, 2);
            $table->string('method', 20);
            $table->string('paid_to')->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('event', 40);
            $table->string('language', 2);
            $table->text('body');
            $table->timestamps();
            $table->unique(['event', 'language']);
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event', 40)->index();
            $table->string('channel', 20);
            $table->string('provider', 30);
            $table->string('phone', 20);
            $table->text('message');
            $table->string('status', 20)->default('queued')->index();
            $table->text('error')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('related');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('preferred_language', 2)->nullable()->after('discount_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('preferred_language');
        });

        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('message_templates');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('payment_allocations');
    }
};
