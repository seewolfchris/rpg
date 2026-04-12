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
        $hasReviewedBy = Schema::hasColumn('encyclopedia_entries', 'reviewed_by');
        $hasReviewedAt = Schema::hasColumn('encyclopedia_entries', 'reviewed_at');

        if (! $hasReviewedBy || ! $hasReviewedAt) {
            Schema::table('encyclopedia_entries', function (Blueprint $table) use ($hasReviewedBy, $hasReviewedAt): void {
                if (! $hasReviewedBy) {
                    $table->foreignId('reviewed_by')
                        ->nullable()
                        ->after('updated_by')
                        ->constrained('users')
                        ->nullOnDelete();
                }

                if (! $hasReviewedAt) {
                    $table->timestamp('reviewed_at')
                        ->nullable()
                        ->after('reviewed_by');
                }
            });
        }

        if (! Schema::hasTable('encyclopedia_entry_revisions')) {
            Schema::create('encyclopedia_entry_revisions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('encyclopedia_entry_id')
                    ->constrained('encyclopedia_entries')
                    ->cascadeOnDelete();
                $table->foreignId('editor_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->string('title_before', 150);
                $table->text('excerpt_before')->nullable();
                $table->longText('content_before');
                $table->string('status_before', 20);
                $table->timestamp('created_at')->useCurrent();

                $table->index(['encyclopedia_entry_id', 'created_at']);
                $table->index(['editor_id', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('encyclopedia_entry_revisions');

        Schema::table('encyclopedia_entries', function (Blueprint $table): void {
            if (Schema::hasColumn('encyclopedia_entries', 'reviewed_by')) {
                $table->dropConstrainedForeignId('reviewed_by');
            }

            if (Schema::hasColumn('encyclopedia_entries', 'reviewed_at')) {
                $table->dropColumn('reviewed_at');
            }
        });
    }
};
