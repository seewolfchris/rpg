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
        Schema::create('character_progression_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('scene_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 40);
            $table->integer('xp_delta')->default(0);
            $table->unsignedSmallInteger('level_before')->default(1);
            $table->unsignedSmallInteger('level_after')->default(1);
            $table->integer('ap_delta')->default(0);
            $table->json('attribute_deltas')->nullable();
            $table->string('reason', 500)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['character_id', 'created_at']);
            $table->index(['campaign_id', 'created_at']);
            $table->index(['scene_id', 'created_at']);
            $table->index(['actor_user_id', 'created_at']);
            $table->index(['event_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('character_progression_events');
    }
};
