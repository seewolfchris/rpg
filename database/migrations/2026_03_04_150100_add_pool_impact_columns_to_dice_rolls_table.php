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
        Schema::table('dice_rolls', function (Blueprint $table): void {
            $table->smallInteger('applied_le_delta')->default(0)->after('total');
            $table->smallInteger('applied_ae_delta')->default(0)->after('applied_le_delta');
            $table->unsignedSmallInteger('resulting_le_current')->nullable()->after('applied_ae_delta');
            $table->unsignedSmallInteger('resulting_ae_current')->nullable()->after('resulting_le_current');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dice_rolls', function (Blueprint $table): void {
            $table->dropColumn([
                'applied_le_delta',
                'applied_ae_delta',
                'resulting_le_current',
                'resulting_ae_current',
            ]);
        });
    }
};
