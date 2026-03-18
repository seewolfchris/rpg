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
        Schema::table('scenes', function (Blueprint $table): void {
            $table->foreignId('previous_scene_id')
                ->nullable()
                ->after('slug')
                ->constrained('scenes')
                ->nullOnDelete();
            $table->string('header_image_path')->nullable()->after('description');
            $table->string('mood', 20)->default('neutral')->after('status');

            $table->index(['campaign_id', 'previous_scene_id']);
            $table->index(['campaign_id', 'mood']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scenes', function (Blueprint $table): void {
            $table->dropIndex(['campaign_id', 'previous_scene_id']);
            $table->dropIndex(['campaign_id', 'mood']);
            $table->dropConstrainedForeignId('previous_scene_id');
            $table->dropColumn(['header_image_path', 'mood']);
        });
    }
};
