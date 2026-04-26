<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('handouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scene_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->timestamp('revealed_at')->nullable();
            $table->string('version_label', 80)->nullable();
            $table->unsignedInteger('sort_order')->nullable();
            $table->timestamps();

            $table->index(
                ['campaign_id', 'revealed_at', 'sort_order', 'id'],
                'handouts_campaign_revealed_sort_idx'
            );
            $table->index(
                ['campaign_id', 'scene_id', 'revealed_at', 'sort_order', 'id'],
                'handouts_campaign_scene_revealed_sort_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handouts');
    }
};
