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
        Schema::create('post_moderation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('moderator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('previous_status', 20);
            $table->string('new_status', 20);
            $table->string('reason', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['post_id', 'created_at']);
            $table->index(['moderator_id', 'created_at']);
            $table->index(['new_status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_moderation_logs');
    }
};
