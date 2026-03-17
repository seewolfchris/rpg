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
        Schema::table('characters', function (Blueprint $table) {
            $table->unsignedInteger('xp_total')->default(0)->after('ae_current');
            $table->unsignedSmallInteger('level')->default(1)->after('xp_total');
            $table->unsignedSmallInteger('attribute_points_unspent')->default(0)->after('level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn([
                'xp_total',
                'level',
                'attribute_points_unspent',
            ]);
        });
    }
};
