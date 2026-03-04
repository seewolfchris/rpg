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
            $table->foreignId('post_id')
                ->nullable()
                ->after('scene_id')
                ->constrained()
                ->nullOnDelete()
                ->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dice_rolls', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('post_id');
        });
    }
};
