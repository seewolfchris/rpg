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
            $table->unsignedSmallInteger('mu_current')->nullable();
            $table->unsignedSmallInteger('kl_current')->nullable();
            $table->unsignedSmallInteger('in_current')->nullable();
            $table->unsignedSmallInteger('ch_current')->nullable();
            $table->unsignedSmallInteger('ff_current')->nullable();
            $table->unsignedSmallInteger('ge_current')->nullable();
            $table->unsignedSmallInteger('ko_current')->nullable();
            $table->unsignedSmallInteger('kk_current')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn([
                'mu_current',
                'kl_current',
                'in_current',
                'ch_current',
                'ff_current',
                'ge_current',
                'ko_current',
                'kk_current',
            ]);
        });
    }
};
