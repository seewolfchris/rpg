<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scene_conflict_actors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scene_id')->constrained()->cascadeOnDelete();
            $table->string('actor_type', 16);
            $table->foreignId('character_id')->nullable()->constrained('characters')->nullOnDelete();
            $table->string('name', 120);
            $table->unsignedSmallInteger('le_current')->nullable();
            $table->unsignedSmallInteger('le_max')->nullable();
            $table->unsignedSmallInteger('ae_current')->nullable();
            $table->unsignedSmallInteger('ae_max')->nullable();
            $table->unsignedSmallInteger('attack_value')->nullable();
            $table->unsignedSmallInteger('defense_value')->nullable();
            $table->unsignedSmallInteger('armor_protection')->nullable();
            $table->unsignedSmallInteger('damage_value')->nullable();
            $table->unsignedSmallInteger('spell_value')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'scene_id'], 'scene_conflict_actors_campaign_scene_idx');
            $table->index(['scene_id', 'actor_type'], 'scene_conflict_actors_scene_type_idx');
            $table->index('character_id', 'scene_conflict_actors_character_idx');
            $table->unique(['scene_id', 'character_id'], 'scene_conflict_actors_scene_character_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scene_conflict_actors');
    }
};

