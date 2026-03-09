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
        Schema::connection(config('webpush.database_connection'))
            ->create(config('webpush.table_name'), function (Blueprint $table): void {
                $table->id();
                $table->morphs('subscribable', 'push_subscriptions_subscribable_morph_idx');
                $table->foreignId('user_id')
                    ->constrained()
                    ->cascadeOnDelete();
                $table->foreignId('world_id')
                    ->constrained('worlds')
                    ->cascadeOnDelete();
                $table->string('endpoint', 500);
                $table->string('public_key')->nullable();
                $table->string('auth_token')->nullable();
                $table->string('content_encoding')->nullable();
                $table->timestamps();

                $table->unique('endpoint', 'push_subscriptions_endpoint_unique');
                $table->unique(['user_id', 'endpoint'], 'push_subscriptions_user_endpoint_unique');
                $table->index(['world_id', 'user_id'], 'push_subscriptions_world_user_idx');
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('webpush.database_connection'))
            ->dropIfExists(config('webpush.table_name'));
    }
};

