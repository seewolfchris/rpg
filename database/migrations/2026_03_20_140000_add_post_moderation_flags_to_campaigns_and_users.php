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
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->boolean('requires_post_moderation')
                ->default(false)
                ->after('is_public');
            $table->index(['requires_post_moderation', 'created_at'], 'campaigns_requires_post_moderation_created_at_idx');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('can_post_without_moderation')
                ->default(false)
                ->after('role');
            $table->index(['can_post_without_moderation', 'role'], 'users_can_post_without_moderation_role_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropIndex('campaigns_requires_post_moderation_created_at_idx');
            $table->dropColumn('requires_post_moderation');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_can_post_without_moderation_role_idx');
            $table->dropColumn('can_post_without_moderation');
        });
    }
};
