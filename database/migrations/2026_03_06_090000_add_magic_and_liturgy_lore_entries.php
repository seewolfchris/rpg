<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        DB::table('encyclopedia_categories')->upsert([
            [
                'name' => 'Magie & Liturgie',
                'slug' => 'magie-liturgie',
                'summary' => 'Verbotene Riten, gebrochene Schwüre und der Preis astraler Macht.',
                'position' => 60,
                'is_public' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['slug'], ['name', 'summary', 'position', 'is_public', 'updated_at']);

        $categoryId = DB::table('encyclopedia_categories')
            ->where('slug', 'magie-liturgie')
            ->value('id');

        if (! is_numeric($categoryId)) {
            return;
        }

        $entries = [
            [
                'title' => 'Astralwunden und Funkenzoll',
                'slug' => 'astralwunden-und-funkenzoll',
                'excerpt' => 'Magie wird in Vhal\'Tor nicht gewirkt, sondern mit Narben bezahlt.',
                'position' => 10,
                'content' => <<<'MD'
Magie in Vhal'Tor ist kein Geschenk, sondern ein Kredit, den der Körper mit Zögern, Zittern und Erinnerungslücken tilgt. Wer Zauber wirkt, reißt jedes Mal ein feines Stück Haut von der unsichtbaren Grenze zwischen Geist und Glut. Diese Risse nennen Heiler Astralwunden. Sie bluten selten sichtbar, aber sie verändern Schlaf, Sprache und Temperament. Manche Magiebegabte werden still wie Stein. Andere hören nachts Stimmen aus den Trümmern alter Schwüre.

In den [Splitterhainen](/wissen/enzyklopaedie/voelker-spezies/elfen-der-splitterhaine) sprechen Älteste vom Funkenzoll: Jeder gelungene Effekt verlangt eine Gegengabe. Das kann Müdigkeit sein, verlorene Wärme in den Fingern oder ein Name, der einem plötzlich nicht mehr einfällt. Wer diese kleinen Preise ignoriert, zahlt später groß. Besonders in der Nähe einer [Blutpforte](/wissen/enzyklopaedie/kernausdruecke/blutpforte) wächst der Zoll unberechenbar und frisst sich tief in die Person.

Die [Glutverlorenen](/wissen/enzyklopaedie/voelker-spezies/glutverlorene) gelten als lebende Warnung für jene, die Macht ohne Maß suchen. Sie sind nicht das Ergebnis böser Absicht, sondern von zu viel Kraft in zu kurzer Zeit.

Und wenn ein Zauber ohne Preis gelingt, ist das meist nur die Ruhe vor einer größeren Rechnung.
MD,
                'game_relevance' => [
                    'ae_hint' => 'Astralenergie ist begrenzt und regeneriert nicht folgenlos; nicht-magische Figuren starten weiterhin mit AE=0.',
                    'probe_hint' => 'Typische GM-Proben: Klugheit/Intuition zur Kontrolle, Mut bei Überlastung oder Kontrollverlust.',
                    'real_world_hint' => 'Real-World-Anfänger beginnen ohne Magiefähigkeit und ohne AE; magische Entwicklung ist nur ingame möglich.',
                ],
            ],
            [
                'title' => 'Liturgischer Letztschwur',
                'slug' => 'liturgischer-letztschwur',
                'excerpt' => 'Liturgie bindet nicht nur Götter, sondern vor allem denjenigen, der sie ausspricht.',
                'position' => 20,
                'content' => <<<'MD'
Der liturgische Letztschwur ist der gefährlichste Bund zwischen Stimme, Blut und Ordnung. Priester und Ordensrichter nutzen ihn, wenn ein gewöhnlicher Eid nicht mehr trägt: bei Massenpanik, Häresie oder offenen Bürgerkriegen. Im Kern ist der Ritus einfach und grausam. Der Schwörende legt fest, was geschehen muss, und bietet sich selbst als Pfand an, falls das Ziel scheitert.

Die [Glutrichter](/wissen/enzyklopaedie/machtbloecke/orden-der-glutrichter) betrachten diesen Schwur als letzte legitime Waffe gegen religiösen Zerfall. Doch auch sie wissen, dass jeder Letztschwur Spuren hinterlässt: verbrannte Stimmbänder, starre Hände, unheilbare Schlaflosigkeit. Bei wiederholter Anwendung entstehen oft jene brüchigen Persönlichkeiten, die später als [Schwurgeborene](/wissen/enzyklopaedie/voelker-spezies/schwurgeborene) in den Chroniken auftauchen.

In ländlichen Regionen wird der Ritus heimlich kopiert, meist unvollständig und ohne Schutzformeln. Das Ergebnis sind gebrochene Dorfbünde, in denen Kinder an Schwüre gebunden werden, die sie nie gesprochen haben. Solche Praktiken gelten überall als verboten, werden aber in Hungerjahren trotzdem benutzt.

Der Letztschwur kann einen Ort retten, doch er nimmt fast immer denjenigen, der ihn trägt.
MD,
                'game_relevance' => [
                    'le_hint' => 'Liturgische Eskalationen können direkte LE-Kosten oder langfristige Erschöpfung begründen.',
                    'probe_hint' => 'Geeignet für GM-Proben auf Mut/Charisma/Klugheit bei Eidbruch, Fanatismus und sozialem Druck.',
                    'real_world_hint' => 'Für Real-World-Figuren ist Liturgie zu Beginn eher Fremdwissen als aktive Fähigkeit.',
                ],
            ],
            [
                'title' => 'Blutpfortenrituale',
                'slug' => 'blutpfortenrituale',
                'excerpt' => 'Rituale an Blutpforten öffnen Wege, die mehr fordern, als jede Gruppe tragen kann.',
                'position' => 30,
                'content' => <<<'MD'
Ein Blutpfortenritual beginnt selten mit Größenwahn. Meist beginnt es mit Verzweiflung: belagerte Städte, sterbende Karawanen, eine letzte Chance gegen Übermacht. Rituale an einer [Blutpforte](/wissen/enzyklopaedie/kernausdruecke/blutpforte) versprechen Abkürzungen durch Raum, Zeit oder Erinnerung, doch jeder Schritt durch das Tor schneidet etwas aus den Beteiligten heraus. Mal ist es nur Kraft. Mal sind es Bindungen, Namen oder die Fähigkeit zu trauern.

In der [Nebelmark](/wissen/enzyklopaedie/regionen/nebelmark) arbeiten versteckte Zirkel mit sogenannten Dreifachpfändern: Blut, Aussage, Besitz. Wer alle drei bietet, erhält oft den stärksten Effekt, verliert jedoch fast immer die Kontrolle über die Folgeschäden. Deshalb verfolgen die Glutrichter diese Zirkel mit besonderer Härte, obwohl sie ihre Dienste in Grenzkrisen heimlich selbst anfordern.

Viele [Glutverlorene](/wissen/enzyklopaedie/voelker-spezies/glutverlorene) stammen aus misslungenen Pfortenritualen. Sie sind der lebende Beweis, dass ein „knapp gelungenes“ Ritual meist nur ein langsames Scheitern ist. Jeder überlebte Durchgang macht den nächsten verführerischer und tödlicher zugleich.

Wer einmal durch eine Blutpforte ging, betritt nie wieder dieselbe Welt wie zuvor.
MD,
                'game_relevance' => [
                    'le_hint' => 'Pfortenrituale eignen sich als harte Story-Kosten mit unmittelbaren LE/AE-Konsequenzen.',
                    'ae_hint' => 'Hoher astraler Verbrauch mit erhöhtem Risiko für Kontrollverlust; nicht-magische Figuren können nur indirekt beteiligt sein.',
                    'probe_hint' => 'Typische GM-Proben: Intuition/Klugheit zur Ritualstabilität, Mut bei Öffnung und Nachhall.',
                ],
            ],
            [
                'title' => 'Nullfelder der Asche',
                'slug' => 'nullfelder-der-asche',
                'excerpt' => 'In Nullfeldern verstummt Magie, doch das Schweigen selbst macht Menschen wahnsinnig.',
                'position' => 40,
                'content' => <<<'MD'
Nullfelder sind Zonen, in denen Magie nicht nur schwächer wird, sondern vollständig verstummt. Kein Flüstern, kein Funken, kein Echo. Für Außenstehende klingt das nach Sicherheit. Für Magiebegabte fühlt es sich an wie Ersticken bei offenem Himmel. Viele beschreiben den ersten Schritt ins Nullfeld als abrupten Absturz: als würde ein zweites Herz aufhören zu schlagen.

Die größten Nullfelder liegen in den verwundeten [Aschelanden](/wissen/enzyklopaedie/regionen/aschelande), oft nahe eingestürzter Festungen oder alter Sonnenaltäre. Dort nutzen Militärführer sie als natürliche Sperren gegen Ritualangriffe. Doch auch gewöhnliche Truppen leiden unter dem Effekt: Orientierung bricht weg, Schlaf wird flach, Aggression steigt. Sogar die standhaften [Zwerge der Basaltkämme](/wissen/enzyklopaedie/voelker-spezies/zwerge-der-basaltkaemme) meiden längere Lager in solchen Zonen.

Schmuggler und Söldner schätzen Nullfelder trotzdem, weil dort viele magische Nachverfolgungen ins Leere laufen. Was wie taktischer Vorteil wirkt, kippt oft in Paranoia. Wer zu lange im Nullfeld lebt, beginnt überall Verrat zu wittern, selbst im eigenen Spiegelbild.

Im Nullfeld schweigt nicht nur die Magie, sondern auch der Trost, den sie sonst vorgaukelt.
MD,
                'game_relevance' => [
                    'ae_hint' => 'In Nullfeldern können AE-basierte Fähigkeiten erzählerisch blockiert oder stark erschwert werden.',
                    'probe_hint' => 'Sinnvoll für GM-Proben auf Konstitution/Mut/Intuition gegen Stress, Panik und Desorientierung.',
                    'rs_hint' => 'Nullfelder sind eher mentale als physische Gefahr; Rüstung ersetzt hier keine stabile Probe.',
                ],
            ],
            [
                'title' => 'Verlorene Schulen von Iskand',
                'slug' => 'verlorene-schulen-von-iskand',
                'excerpt' => 'Die Schulen von Iskand sammelten Wissen über Magie, bis Wissen selbst zum Verbot wurde.',
                'position' => 50,
                'content' => <<<'MD'
Vor dem großen Zerfall galt Iskand als Stadt der offenen Lehrhöfe. Dort stritten Magier, Liturgen, Heiler und Chronisten öffentlich über Wahrheit, Methode und Verantwortung. Die Schulen waren berühmt für ihre Regel: Kein Zauber ohne Gegenprüfung, kein Eid ohne Gegenstimme. Nach dem Aschenfall wurden Archive geplündert, Lehrmeister gehängt und ganze Bibliotheken in Brand gesetzt, weil jede Fraktion nur noch „nützliches“ Wissen behalten wollte.

Heute existieren die Schulen nur als verstreute Fragmente: ein halb verbrannter Traktat in den Händen eines [Menschen der Aschepfade](/wissen/enzyklopaedie/voelker-spezies/menschen-der-aschepfade), eine versiegelte Formel in einem Haus der [Schattenhaeuser von Nerez](/wissen/enzyklopaedie/machtbloecke/schattenhaeuser-von-nerez), ein Liedvers bei den Elfen, der mehr Theorie enthält als mancher Kodex. Wer solche Fragmente zusammensetzt, gewinnt Macht – und wird sofort zur Zielscheibe.

Die wenigen verbliebenen Iskand-Zirkel lehren heute unter Tarnnamen. Sie bilden keine Helden aus, sondern Überlebende mit Gewissen: Leute, die wissen, wann ein Ritual möglich ist und wann es nur Verzweiflung in schöner Sprache bleibt.

In Vhal'Tor ist verbotenes Wissen selten falsch, aber fast immer zu teuer für ein einziges Leben.
MD,
                'game_relevance' => [
                    'probe_hint' => 'Ideal für Wissens-, Recherche- und Deutungsproben auf Klugheit/Intuition im Kampagnenkern.',
                    'ae_hint' => 'Iskand-Wissen begründet magische Optionen, hebt aber die AE-Grundregeln (inkl. AE=0 bei Nicht-Magiern) nicht auf.',
                    'real_world_hint' => 'Real-World-Anfänger können Iskand-Lore zuerst über Fundstücke und Mentoren erschließen.',
                ],
            ],
        ];

        foreach ($entries as $entry) {
            DB::table('encyclopedia_entries')->updateOrInsert(
                [
                    'encyclopedia_category_id' => (int) $categoryId,
                    'slug' => $entry['slug'],
                ],
                [
                    'title' => $entry['title'],
                    'excerpt' => $entry['excerpt'],
                    'content' => $entry['content'],
                    'status' => 'published',
                    'position' => $entry['position'],
                    'game_relevance' => json_encode($entry['game_relevance'], JSON_UNESCAPED_UNICODE),
                    'published_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $categoryId = DB::table('encyclopedia_categories')
            ->where('slug', 'magie-liturgie')
            ->value('id');

        if (! is_numeric($categoryId)) {
            return;
        }

        DB::table('encyclopedia_entries')
            ->where('encyclopedia_category_id', (int) $categoryId)
            ->whereIn('slug', [
                'astralwunden-und-funkenzoll',
                'liturgischer-letztschwur',
                'blutpfortenrituale',
                'nullfelder-der-asche',
                'verlorene-schulen-von-iskand',
            ])
            ->delete();

        DB::table('encyclopedia_categories')
            ->where('id', (int) $categoryId)
            ->delete();
    }
};
