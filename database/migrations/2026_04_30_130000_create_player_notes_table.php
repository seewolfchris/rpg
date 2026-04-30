<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scene_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('character_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 180);
            $table->text('body')->nullable();
            $table->unsignedInteger('sort_order')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('campaign_id');
            $table->index(['user_id', 'campaign_id']);
            $table->index(['user_id', 'campaign_id', 'scene_id']);
            $table->index(['user_id', 'character_id']);
            $table->index(['campaign_id', 'scene_id']);
            $table->index(['user_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_notes');
    }
};
