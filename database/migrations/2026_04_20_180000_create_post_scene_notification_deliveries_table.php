<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_scene_notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel', 24);
            $table->string('status', 16);
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('first_attempted_at')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('last_error', 1000)->nullable();
            $table->timestamps();

            $table->unique(
                ['post_id', 'recipient_user_id', 'channel'],
                'post_scene_notification_deliveries_post_user_channel_unique'
            );
            $table->index(
                ['post_id', 'channel', 'status'],
                'post_scene_notification_deliveries_post_channel_status_idx'
            );
            $table->index(
                ['status', 'updated_at'],
                'post_scene_notification_deliveries_status_updated_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_scene_notification_deliveries');
    }
};

