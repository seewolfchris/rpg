<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_gm_contact_threads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('subject', 180);
            $table->string('status', 32);
            $table->foreignId('character_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('scene_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('last_activity_at');
            $table->timestamps();

            $table->index(
                ['campaign_id', 'last_activity_at', 'id'],
                'gm_contact_threads_campaign_activity_idx'
            );
            $table->index(
                ['campaign_id', 'created_by', 'last_activity_at'],
                'gm_contact_threads_campaign_creator_activity_idx'
            );
            $table->index(
                ['status', 'last_activity_at'],
                'gm_contact_threads_status_activity_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_gm_contact_threads');
    }
};
