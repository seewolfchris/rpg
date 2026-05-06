<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('combat_phase_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('combat_phase_id')->constrained('combat_phases')->cascadeOnDelete();
            $table->unsignedInteger('position');

            $table->string('actor_type', 16);
            $table->foreignId('actor_character_id')->nullable()->constrained('characters')->nullOnDelete();
            $table->string('actor_name', 120)->nullable();
            $table->json('actor_snapshot')->nullable();

            $table->string('target_type', 16);
            $table->foreignId('target_character_id')->nullable()->constrained('characters')->nullOnDelete();
            $table->string('target_name', 120)->nullable();
            $table->json('target_snapshot')->nullable();

            $table->string('weapon_name', 120)->nullable();
            $table->unsignedSmallInteger('attack_target_value');
            $table->string('attack_roll_mode', 32)->default('normal');
            $table->smallInteger('attack_modifier')->default(0);
            $table->string('defense_label', 80)->nullable();
            $table->unsignedSmallInteger('defense_target_value')->nullable();
            $table->string('defense_roll_mode', 32)->nullable();
            $table->smallInteger('defense_modifier')->default(0);
            $table->unsignedSmallInteger('damage')->default(0);
            $table->unsignedSmallInteger('armor_protection')->nullable();
            $table->text('intent_text')->nullable();
            $table->text('resolution_note')->nullable();
            $table->json('result')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['combat_phase_id', 'position'], 'combat_phase_actions_phase_position_idx');
            $table->index('actor_character_id', 'combat_phase_actions_actor_character_idx');
            $table->index('target_character_id', 'combat_phase_actions_target_character_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combat_phase_actions');
    }
};
