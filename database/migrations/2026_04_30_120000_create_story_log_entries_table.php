<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_log_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scene_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 180);
            $table->text('body')->nullable();
            $table->timestamp('revealed_at')->nullable();
            $table->unsignedInteger('sort_order')->nullable();
            $table->timestamps();

            $table->index('campaign_id');
            $table->index(['campaign_id', 'revealed_at']);
            $table->index(['campaign_id', 'scene_id']);
            $table->index(['campaign_id', 'sort_order']);
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_log_entries');
    }
};
