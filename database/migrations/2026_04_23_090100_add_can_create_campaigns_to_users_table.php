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
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('can_create_campaigns')
                ->default(false)
                ->after('can_post_without_moderation');
            $table->index(['can_create_campaigns', 'role'], 'users_can_create_campaigns_role_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_can_create_campaigns_role_idx');
            $table->dropColumn('can_create_campaigns');
        });
    }
};
