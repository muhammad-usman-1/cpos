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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('shop_name')->nullable();
            $table->string('phone', 20)->nullable()->index();
            $table->string('city', 80)->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('balance', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->morphs('party');
            $table->date('entry_date')->index();
            $table->string('type', 30);
            $table->string('description');
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->nullableMorphs('reference');
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->nullable()->unique();
            $table->morphs('party');
            $table->string('direction', 3);
            $table->date('payment_date')->index();
            $table->decimal('amount', 14, 2);
            $table->string('method', 20);
            $table->string('reference_no', 80)->nullable();
            $table->string('notes')->nullable();
            $table->nullableMorphs('source');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('suppliers');
    }
};
