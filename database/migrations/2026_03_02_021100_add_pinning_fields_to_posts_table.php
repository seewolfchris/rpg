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
            $table->boolean('is_pinned')->default(false)->after('is_edited');
            $table->timestamp('pinned_at')->nullable()->after('is_pinned');
            $table->foreignId('pinned_by')
                ->nullable()
                ->after('pinned_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['scene_id', 'is_pinned', 'pinned_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['scene_id', 'is_pinned', 'pinned_at']);
            $table->dropConstrainedForeignId('pinned_by');
            $table->dropColumn(['is_pinned', 'pinned_at']);
        });
    }
};
