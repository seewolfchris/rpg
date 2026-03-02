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
        Schema::create('scene_bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scene_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->string('label', 80)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'scene_id']);
            $table->index(['user_id', 'updated_at']);
            $table->index(['scene_id', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scene_bookmarks');
    }
};
