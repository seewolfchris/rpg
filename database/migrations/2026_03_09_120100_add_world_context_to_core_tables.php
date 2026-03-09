<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $defaultWorldId = (int) DB::table('worlds')
            ->where('slug', 'chroniken-der-asche')
            ->value('id');

        if ($defaultWorldId <= 0) {
            throw new RuntimeException('Default world "chroniken-der-asche" was not found.');
        }

        Schema::table('campaigns', function (Blueprint $table) use ($defaultWorldId): void {
            $table->foreignId('world_id')
                ->default($defaultWorldId)
                ->after('id')
                ->constrained('worlds')
                ->restrictOnDelete();
            $table->index(['world_id', 'status', 'is_public']);
        });

        Schema::table('characters', function (Blueprint $table) use ($defaultWorldId): void {
            $table->foreignId('world_id')
                ->default($defaultWorldId)
                ->after('user_id')
                ->constrained('worlds')
                ->restrictOnDelete();
            $table->index(['world_id', 'user_id']);
        });

        Schema::table('encyclopedia_categories', function (Blueprint $table) use ($defaultWorldId): void {
            $table->foreignId('world_id')
                ->default($defaultWorldId)
                ->after('id')
                ->constrained('worlds')
                ->restrictOnDelete();
        });

        DB::table('campaigns')
            ->whereNull('world_id')
            ->update(['world_id' => $defaultWorldId]);

        DB::table('characters')
            ->whereNull('world_id')
            ->update(['world_id' => $defaultWorldId]);

        DB::table('encyclopedia_categories')
            ->whereNull('world_id')
            ->update(['world_id' => $defaultWorldId]);

        Schema::table('encyclopedia_categories', function (Blueprint $table): void {
            $table->dropUnique('encyclopedia_categories_slug_unique');
            $table->unique(['world_id', 'slug']);
            $table->index(['world_id', 'position', 'is_public']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('encyclopedia_categories', function (Blueprint $table): void {
            $table->dropUnique(['world_id', 'slug']);
            $table->dropIndex(['world_id', 'position', 'is_public']);
            $table->unique('slug');
            $table->dropConstrainedForeignId('world_id');
        });

        Schema::table('characters', function (Blueprint $table): void {
            $table->dropIndex(['world_id', 'user_id']);
            $table->dropConstrainedForeignId('world_id');
        });

        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropIndex(['world_id', 'status', 'is_public']);
            $table->dropConstrainedForeignId('world_id');
        });
    }
};
