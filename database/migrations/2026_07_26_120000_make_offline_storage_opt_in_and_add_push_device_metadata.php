<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('offline_queue_enabled')
                ->default(false)
                ->change();
        });

        // The previous opt-out design did not record whether a true value represented
        // an informed choice. Reset it once so every user must opt in explicitly.
        DB::table('users')->update([
            'offline_queue_enabled' => false,
        ]);

        Schema::connection(config('webpush.database_connection'))
            ->table(config('webpush.table_name'), function (Blueprint $table): void {
                $table->string('device_name', 80)->nullable()->after('content_encoding');
                $table->timestamp('last_used_at')->nullable()->after('device_name');
            });
    }

    public function down(): void
    {
        Schema::connection(config('webpush.database_connection'))
            ->table(config('webpush.table_name'), function (Blueprint $table): void {
                $table->dropColumn(['device_name', 'last_used_at']);
            });

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('offline_queue_enabled')
                ->default(true)
                ->change();
        });
    }
};
