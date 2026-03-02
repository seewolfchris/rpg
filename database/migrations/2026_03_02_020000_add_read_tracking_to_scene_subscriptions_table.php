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
        Schema::table('scene_subscriptions', function (Blueprint $table) {
            $table->foreignId('last_read_post_id')
                ->nullable()
                ->after('is_muted')
                ->constrained('posts')
                ->nullOnDelete();
            $table->timestamp('last_read_at')->nullable()->after('last_read_post_id');

            $table->index(['user_id', 'last_read_post_id']);
            $table->index(['scene_id', 'last_read_post_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scene_subscriptions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'last_read_post_id']);
            $table->dropIndex(['scene_id', 'last_read_post_id']);
            $table->dropConstrainedForeignId('last_read_post_id');
            $table->dropColumn('last_read_at');
        });
    }
};
