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
        Schema::create('point_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id');
            $table->string('event_key', 40);
            $table->integer('points');
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'source_type', 'source_id', 'event_key'], 'point_events_unique_source_event');
            $table->index(['user_id', 'created_at']);
            $table->index(['source_type', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('point_events');
    }
};
