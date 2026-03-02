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
        Schema::create('dice_rolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scene_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('character_id')->nullable()->constrained()->nullOnDelete();
            $table->string('roll_mode', 20)->default('normal');
            $table->smallInteger('modifier')->default(0);
            $table->string('label', 80)->nullable();
            $table->json('rolls');
            $table->unsignedTinyInteger('kept_roll');
            $table->smallInteger('total');
            $table->boolean('is_critical_success')->default(false);
            $table->boolean('is_critical_failure')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['scene_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['scene_id', 'roll_mode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dice_rolls');
    }
};
