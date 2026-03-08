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
        Schema::table('posts', function (Blueprint $table) {
            $table->index(['scene_id', 'id'], 'posts_scene_id_id_idx');
        });

        Schema::table('scene_subscriptions', function (Blueprint $table) {
            $table->index(['user_id', 'scene_id'], 'scene_sub_user_scene_idx');
            $table->index(['scene_id', 'last_read_post_id', 'user_id'], 'scene_sub_scene_read_user_idx');
        });

        Schema::table('campaign_invitations', function (Blueprint $table) {
            $table->index(['campaign_id', 'status', 'user_id'], 'camp_inv_campaign_status_user_idx');
            $table->index(['user_id', 'status', 'role'], 'camp_inv_user_status_role_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_invitations', function (Blueprint $table) {
            $table->dropIndex('camp_inv_campaign_status_user_idx');
            $table->dropIndex('camp_inv_user_status_role_idx');
        });

        Schema::table('scene_subscriptions', function (Blueprint $table) {
            $table->dropIndex('scene_sub_user_scene_idx');
            $table->dropIndex('scene_sub_scene_read_user_idx');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_scene_id_id_idx');
        });
    }
};
