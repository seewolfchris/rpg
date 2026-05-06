<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('combat_phases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scene_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('phase_number');
            $table->string('status', 32);
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->json('resolution_summary')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'scene_id', 'status'], 'combat_phases_campaign_scene_status_idx');
            $table->unique(['scene_id', 'phase_number'], 'combat_phases_scene_phase_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combat_phases');
    }
};
