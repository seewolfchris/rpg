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
        Schema::create('post_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('editor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('character_id')->nullable()->constrained()->nullOnDelete();
            $table->string('post_type', 10);
            $table->string('content_format', 20)->default('markdown');
            $table->longText('content');
            $table->json('meta')->nullable();
            $table->string('moderation_status', 20)->default('pending');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['post_id', 'version']);
            $table->index(['post_id', 'created_at']);
            $table->index(['editor_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_revisions');
    }
};
