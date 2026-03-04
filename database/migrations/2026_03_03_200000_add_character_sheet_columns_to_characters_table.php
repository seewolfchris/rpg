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
        Schema::table('characters', function (Blueprint $table): void {
            $table->string('origin', 50)->nullable();
            $table->string('species', 30)->nullable();
            $table->string('calling', 40)->nullable();
            $table->string('calling_custom_name', 120)->nullable();
            $table->text('calling_custom_description')->nullable();

            $table->string('concept', 180)->nullable();
            $table->text('gm_secret')->nullable();
            $table->text('world_connection')->nullable();
            $table->text('gm_note')->nullable();

            $table->unsignedTinyInteger('mu')->nullable();
            $table->unsignedTinyInteger('kl')->nullable();
            $table->unsignedTinyInteger('in')->nullable();
            $table->unsignedTinyInteger('ch')->nullable();
            $table->unsignedTinyInteger('ff')->nullable();
            $table->unsignedTinyInteger('ge')->nullable();
            $table->unsignedTinyInteger('ko')->nullable();
            $table->unsignedTinyInteger('kk')->nullable();

            $table->json('advantages')->nullable();
            $table->json('disadvantages')->nullable();

            $table->unsignedSmallInteger('le_max')->nullable();
            $table->unsignedSmallInteger('le_current')->nullable();
            $table->unsignedSmallInteger('ae_max')->nullable();
            $table->unsignedSmallInteger('ae_current')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table): void {
            $table->dropColumn([
                'origin',
                'species',
                'calling',
                'calling_custom_name',
                'calling_custom_description',
                'concept',
                'gm_secret',
                'world_connection',
                'gm_note',
                'mu',
                'kl',
                'in',
                'ch',
                'ff',
                'ge',
                'ko',
                'kk',
                'advantages',
                'disadvantages',
                'le_max',
                'le_current',
                'ae_max',
                'ae_current',
            ]);
        });
    }
};
