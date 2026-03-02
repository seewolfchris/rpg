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
        Schema::create('scenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('slug', 170);
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();
            $table->string('status', 20)->default('open');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('allow_ooc')->default(true);
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'slug']);
            $table->index(['campaign_id', 'position']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scenes');
    }
};
