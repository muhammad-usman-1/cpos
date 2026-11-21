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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('phone', 20)->unique();
            $table->string('alt_phone', 20)->nullable()->index();
            $table->string('address')->nullable();
            $table->string('city', 80)->nullable();
            $table->text('notes')->nullable();
            $table->string('tag', 20)->nullable();
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->date('birthday')->nullable();
            $table->date('anniversary')->nullable();
            $table->decimal('balance', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('template', 10)->default('gents');
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('measurement_fields', function (Blueprint $table) {
            $table->id();
            $table->string('template', 10);
            $table->string('key', 50);
            $table->string('label', 80);
            $table->string('group', 10)->default('body');
            $table->string('type', 10)->default('number');
            $table->json('options')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['template', 'key']);
        });

        Schema::create('measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->boolean('is_current')->default(true);
            $table->string('unit', 4)->default('in');
            $table->text('notes')->nullable();
            $table->string('photo_path')->nullable();
            $table->foreignId('taken_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['customer_profile_id', 'version']);
            $table->index(['customer_profile_id', 'is_current']);
        });

        Schema::create('measurement_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('measurement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('measurement_field_id')->constrained()->cascadeOnDelete();
            $table->string('value', 100);
            $table->unique(['measurement_id', 'measurement_field_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('measurement_values');
        Schema::dropIfExists('measurements');
        Schema::dropIfExists('measurement_fields');
        Schema::dropIfExists('customer_profiles');
        Schema::dropIfExists('customers');
    }
};
