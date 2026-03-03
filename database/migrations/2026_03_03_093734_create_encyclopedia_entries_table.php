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
        Schema::create('encyclopedia_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encyclopedia_category_id')
                ->constrained('encyclopedia_categories')
                ->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('slug', 170);
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->string('status', 20)->default('published');
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['encyclopedia_category_id', 'slug']);
            $table->index(['status', 'published_at']);
            $table->index(['encyclopedia_category_id', 'position']);
        });

        $categories = DB::table('encyclopedia_categories')
            ->whereIn('slug', ['zeitalter', 'machtbloecke', 'regionen', 'kernausdruecke'])
            ->pluck('id', 'slug');

        $entries = [
            [
                'category_slug' => 'zeitalter',
                'title' => 'Zeitalter der Sonnenkronen',
                'slug' => 'zeitalter-der-sonnenkronen',
                'excerpt' => 'Imperiale Hochkultur mit zentraler Liturgie und strenger Erbfolge.',
                'content' => 'In dieser Epoche dominierten die Sonnenkronen den Kontinent mit einem dichten Netz aus Statthaltern, Tempelgerichten und Heerstrassen. Loyalitaet wurde ueber Eide, Blutlinien und den Kult des flammenden Thrones gesichert.',
                'position' => 10,
            ],
            [
                'category_slug' => 'zeitalter',
                'title' => 'Der Aschenfall',
                'slug' => 'der-aschenfall',
                'excerpt' => 'Zerfall der Kronenreiche durch Blutpforten, Seuchen und Thronkriege.',
                'content' => 'Mehrere Blutpforten oeffneten sich zeitgleich, Handelsachsen brachen zusammen und rivalisierende Erbhaeuser fuehrten Stellvertreterkriege. Die alten Reichsgrenzen existieren seitdem nur noch auf Karten aus der Vorkriegszeit.',
                'position' => 20,
            ],
            [
                'category_slug' => 'machtbloecke',
                'title' => 'Orden der Glutrichter',
                'slug' => 'orden-der-glutrichter',
                'excerpt' => 'Dogmatische Richterkaste, jagt ketzerische Ritualmagie.',
                'content' => 'Der Orden versteht sich als letzte legitime Instanz gegen verbotene Liturgie. Glutrichter sprechen Urteile vor Ort, konfiszieren Artefakte und verfuegen ueber bewaffnete Gerichtszuege.',
                'position' => 10,
            ],
            [
                'category_slug' => 'machtbloecke',
                'title' => 'Schattenhaeuser von Nerez',
                'slug' => 'schattenhaeuser-von-nerez',
                'excerpt' => 'Adelsnetzwerk aus Spionage, Schuldbriefen und verdeckten Paktbuendnissen.',
                'content' => 'Die Schattenhaeuser operieren ueber Vermittler und Tarnnamen. Offene Herrschaft ist selten, Einfluss laeuft ueber Kreditketten, Informationshandel und kontrollierte Skandale.',
                'position' => 20,
            ],
            [
                'category_slug' => 'regionen',
                'title' => 'Aschelande',
                'slug' => 'aschelande',
                'excerpt' => 'Verbrannte Grenzprovinzen voller Restfestungen und Pilgerstrassen.',
                'content' => 'Zwischen eingestuerzten Wachtuermen und verlassenen Schanzen lebt eine fragmentierte Bevoelkerung aus Konvoifuehrern, Siedlern und Segenhaendlern. Versorgungslinien sind fragil und selten dauerhaft sicher.',
                'position' => 10,
            ],
            [
                'category_slug' => 'regionen',
                'title' => 'Nebelmark',
                'slug' => 'nebelmark',
                'excerpt' => 'Moorige Handelszone, in der Vertraege oft unter falschen Namen geschlossen werden.',
                'content' => 'Die Nebelmark ist Drehscheibe fuer Waren ohne Herkunftsnachweis. Wer hier handelt, zahlt Nachtzoll oder verschwindet in den Docks ohne Urteil.',
                'position' => 20,
            ],
            [
                'category_slug' => 'kernausdruecke',
                'title' => 'Blutpforte',
                'slug' => 'blutpforte',
                'excerpt' => 'Ritualtor, das Preise in Erinnerung, Blut oder Zeit fordert.',
                'content' => 'Blutpforten sind instabile Uebergaenge, deren Oeffnung nie kostenlos ist. Je groesser der Effekt, desto hoeher der persoenliche Preis fuer den Ausloeser.',
                'position' => 10,
            ],
            [
                'category_slug' => 'kernausdruecke',
                'title' => 'Aschesiegel',
                'slug' => 'aschesiegel',
                'excerpt' => 'Brandzeichen zur Markierung gebundener Artefakte und Eidtraeger.',
                'content' => 'Ein Aschesiegel dient als Herkunftsnachweis und als Zwangsbindung. Das Brechen eines Siegels gilt in vielen Regionen als Schwerverbrechen.',
                'position' => 20,
            ],
        ];

        $now = now();

        foreach ($entries as $entry) {
            $categoryId = $categories[$entry['category_slug']] ?? null;

            if (! $categoryId) {
                continue;
            }

            DB::table('encyclopedia_entries')->insert([
                'encyclopedia_category_id' => $categoryId,
                'title' => $entry['title'],
                'slug' => $entry['slug'],
                'excerpt' => $entry['excerpt'],
                'content' => $entry['content'],
                'status' => 'published',
                'position' => $entry['position'],
                'published_at' => $now,
                'created_by' => null,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('encyclopedia_entries');
    }
};
