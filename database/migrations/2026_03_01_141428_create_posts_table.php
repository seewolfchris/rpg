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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scene_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('character_id')->nullable()->constrained()->nullOnDelete();
            $table->string('post_type', 10)->default('ic');
            $table->string('content_format', 20)->default('markdown');
            $table->longText('content');
            $table->json('meta')->nullable();
            $table->string('moderation_status', 20)->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_edited')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();

            $table->index(['scene_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['moderation_status', 'created_at']);
            $table->index(['post_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
