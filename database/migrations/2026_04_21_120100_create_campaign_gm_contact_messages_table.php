<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_gm_contact_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('thread_id')->constrained('campaign_gm_contact_threads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->timestamps();

            $table->index(
                ['thread_id', 'created_at', 'id'],
                'gm_contact_messages_thread_created_idx'
            );
            $table->index(
                ['user_id', 'created_at'],
                'gm_contact_messages_user_created_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_gm_contact_messages');
    }
};
