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
        Schema::table('scene_subscriptions', function (Blueprint $table): void {
            $table->index(['user_id', 'updated_at'], 'scene_sub_user_updated_idx');
        });

        Schema::table('campaign_invitations', function (Blueprint $table): void {
            $table->index(['user_id', 'status', 'created_at'], 'camp_inv_user_status_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_invitations', function (Blueprint $table): void {
            $table->dropIndex('camp_inv_user_status_created_idx');
        });

        Schema::table('scene_subscriptions', function (Blueprint $table): void {
            $table->dropIndex('scene_sub_user_updated_idx');
        });
    }
};
