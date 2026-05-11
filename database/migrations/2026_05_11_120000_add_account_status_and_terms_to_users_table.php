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
            $table->string('status', 20)
                ->default('active')
                ->after('role')
                ->index();

            $table->timestamp('approved_at')
                ->nullable()
                ->after('status');
            $table->foreignId('approved_by')
                ->nullable()
                ->after('approved_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('suspended_at')
                ->nullable()
                ->after('approved_by');
            $table->foreignId('suspended_by')
                ->nullable()
                ->after('suspended_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->text('status_reason')
                ->nullable()
                ->after('suspended_by');

            $table->timestamp('terms_accepted_at')
                ->nullable()
                ->after('status_reason');
            $table->string('terms_version')
                ->nullable()
                ->after('terms_accepted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('suspended_by');
            $table->dropIndex(['status']);
            $table->dropColumn([
                'status',
                'approved_at',
                'suspended_at',
                'status_reason',
                'terms_accepted_at',
                'terms_version',
            ]);
        });
    }
};
