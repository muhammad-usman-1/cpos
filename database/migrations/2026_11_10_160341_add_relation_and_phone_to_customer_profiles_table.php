<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->string('relation', 20)->nullable()->after('name');
            $table->string('phone', 20)->nullable()->after('relation')->index();
        });

        // Older profiles kept the relation in the name ("Self", "Wife", "Son").
        foreach (['self', 'father', 'mother', 'husband', 'wife', 'son', 'daughter', 'brother', 'sister'] as $relation) {
            DB::table('customer_profiles')->whereRaw('LOWER(name) = ?', [$relation])->update(['relation' => $relation]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->dropIndex(['phone']);
            $table->dropColumn(['relation', 'phone']);
        });
    }
};
