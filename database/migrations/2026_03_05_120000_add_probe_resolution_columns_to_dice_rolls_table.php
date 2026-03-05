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
            $table->string('probe_attribute_key', 12)
                ->nullable()
                ->after('label');
            $table->unsignedTinyInteger('probe_target_value')
                ->nullable()
                ->after('probe_attribute_key');
            $table->boolean('probe_is_success')
                ->nullable()
                ->after('probe_target_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dice_rolls', function (Blueprint $table): void {
            $table->dropColumn([
                'probe_attribute_key',
                'probe_target_value',
                'probe_is_success',
            ]);
        });
    }
};
