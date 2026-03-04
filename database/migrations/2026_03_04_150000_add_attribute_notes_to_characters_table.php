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
        Schema::table('characters', function (Blueprint $table): void {
            $table->string('mu_note', 800)->nullable()->after('mu');
            $table->string('kl_note', 800)->nullable()->after('kl');
            $table->string('in_note', 800)->nullable()->after('in');
            $table->string('ch_note', 800)->nullable()->after('ch');
            $table->string('ff_note', 800)->nullable()->after('ff');
            $table->string('ge_note', 800)->nullable()->after('ge');
            $table->string('ko_note', 800)->nullable()->after('ko');
            $table->string('kk_note', 800)->nullable()->after('kk');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table): void {
            $table->dropColumn([
                'mu_note',
                'kl_note',
                'in_note',
                'ch_note',
                'ff_note',
                'ge_note',
                'ko_note',
                'kk_note',
            ]);
        });
    }
};
