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
        Schema::create('worlds', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('tagline', 180)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'position']);
        });

        $now = now();

        DB::table('worlds')->insert([
            [
                'name' => 'Chroniken der Asche',
                'slug' => 'chroniken-der-asche',
                'tagline' => 'Duestere Fantasy in den Aschelanden.',
                'description' => 'Die etablierte Dark-Fantasy-Welt mit Fokus auf Intrige und Ueberleben.',
                'is_active' => true,
                'position' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Kriminalfaelle',
                'slug' => 'kriminalfaelle',
                'tagline' => 'Ermittlungen, Spuren und graue Wahrheiten.',
                'description' => 'Moderne oder historische Krimi-Settings mit Ermittlungsfokus.',
                'is_active' => true,
                'position' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Gegenwart',
                'slug' => 'gegenwart',
                'tagline' => 'Geschichten im Hier und Jetzt.',
                'description' => 'Realweltliche Kampagnen mit sozialem, dramatischem oder thrillerartigem Fokus.',
                'is_active' => true,
                'position' => 30,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Klassische Fantasy',
                'slug' => 'klassische-fantasy',
                'tagline' => 'Abenteuer zwischen Ruinen, Wundern und alten Reichen.',
                'description' => 'Breit angelegte Fantasy-Kampagnen ohne feste Markenanbindung.',
                'is_active' => true,
                'position' => 40,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Sci-Fi',
                'slug' => 'sci-fi',
                'tagline' => 'Sterne, Stationen und synthetische Konflikte.',
                'description' => 'Zukunftssettings von Near-Future bis Space Opera.',
                'is_active' => true,
                'position' => 50,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Postapokalypse',
                'slug' => 'postapokalypse',
                'tagline' => 'Fragile Ordnung nach dem Zusammenbruch.',
                'description' => 'Kampagnen in zerbrochenen Gesellschaften mit Ressourcenknappheit.',
                'is_active' => true,
                'position' => 60,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('worlds');
    }
};
