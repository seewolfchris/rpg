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
        Schema::create('encyclopedia_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->text('summary')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });

        DB::table('encyclopedia_categories')->insert([
            [
                'name' => 'Zeitalter',
                'slug' => 'zeitalter',
                'summary' => 'Historische Phasen von den Sonnenkronen bis zur Gegenwart.',
                'position' => 10,
                'is_public' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Machtbloecke',
                'slug' => 'machtbloecke',
                'summary' => 'Orden, Haeuser und Bruederschaften mit regionalem Einfluss.',
                'position' => 20,
                'is_public' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Regionen',
                'slug' => 'regionen',
                'summary' => 'Schauplaetze fuer Kampagnen und Szenen.',
                'position' => 30,
                'is_public' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Kernausdruecke',
                'slug' => 'kernausdruecke',
                'summary' => 'Begriffe, die in Szenen regelmaessig auftauchen.',
                'position' => 40,
                'is_public' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('encyclopedia_categories');
    }
};
