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
        Schema::table('products', function (Blueprint $table) {
            $table->string('gender', 10)->nullable()->after('fabric_id')->index();
        });

        // Products saved before this change used ladies piece types (2 piece, 3 piece, shirt or shalwar only).
        DB::table('products')->whereIn('piece_type', ['2_piece', '3_piece', 'shirt_only'])->update(['gender' => 'ladies']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('gender');
        });
    }
};
