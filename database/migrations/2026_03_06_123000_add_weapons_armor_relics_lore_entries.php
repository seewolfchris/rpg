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
                'name' => 'Waffen, Rüstungen & Relikte',
                'slug' => 'waffen-ruestungen-relikte',
                'summary' => 'Ausrüstung der Aschelande mit Feldwerten für Angriff, Parade, Schaden und RS.',
                'position' => 80,
                'is_public' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['slug'], ['name', 'summary', 'position', 'is_public', 'updated_at']);

        $categoryId = DB::table('encyclopedia_categories')
            ->where('slug', 'waffen-ruestungen-relikte')
            ->value('id');

        if (! is_numeric($categoryId)) {
            return;
        }

        $entries = [
            [
                'title' => 'Langschwert der Aschewacht',
                'slug' => 'langschwert-der-aschewacht',
                'excerpt' => 'Bewährte Klingenlinie der Passwachen, stumpf im Aussehen und tödlich in der Führung.',
                'position' => 10,
                'content' => <<<'MD'
Das Langschwert der Aschewacht ist keine Prunkwaffe, sondern ein Werkzeug für lange Frontnächte. Seine Klinge ist etwas breiter als bei höfischen Modellen, der Schwerpunkt liegt näher an der Parierstange, damit auch erschöpfte Träger kontrolliert schlagen können. Viele Schmiede aus [Vesperwall und den Salzpässen](/wissen/enzyklopaedie/regionen/vesperwall-und-die-salzpaesse) markieren die Angel mit kleinen Kerben: eine für jeden bestätigten Wintereinsatz.

Berühmt wurde die Waffe durch gemischte Kompanien aus [Kettenerben](/wissen/enzyklopaedie/voelker-spezies/kettenerben) und Grenzmiliz, die sie in enger Formation führten. Das Schwert lebt nicht von spektakulären Einzelaktionen, sondern von Wiederholbarkeit: sauberer Stand, kurze Linie, kein Kraftverlust. In chaotischen Kämpfen gegen [Aschenwulf-Rudel](/wissen/enzyklopaedie/monster-bestiarium/aschenwulf) gilt diese Verlässlichkeit als entscheidender Vorteil.

Viele junge Helden unterschätzen die Waffe, weil sie schlicht wirkt. Veteranen sehen das anders: Wer mit dem Aschewacht-Schwert verliert, verliert selten wegen der Klinge, sondern wegen Haltung, Timing oder Angst.

### Spielrelevanz (Richtwerte)
- Typ: Waffe
- Angriff: 60 %
- Parade: 58 %
- Schaden pro Treffer: 12

Die Klinge verzeiht keine Eitelkeit, aber sie belohnt jeden, der durchhält.
MD,
                'game_relevance' => [
                    'probe_hint' => 'Richtwerte: Angriff 60 %, Parade 58 %, Schaden 12. Solide Standardwahl für Frontkämpfer.',
                    'rs_hint' => 'Wirkt besonders stabil in Kombination mit mittlerem bis hohem Rüstungsschutz.',
                ],
            ],
            [
                'title' => 'Nebelspieß von Dornhafen',
                'slug' => 'nebelspiess-von-dornhafen',
                'excerpt' => 'Lange Stoßwaffe für Stegkämpfe, Jagd im Dunst und enge Hafenpassagen.',
                'position' => 20,
                'content' => <<<'MD'
Der Nebelspieß entstand in den Stegvierteln von [Dornhafen am Roten Delta](/wissen/enzyklopaedie/regionen/dornhafen-am-roten-delta), wo Kämpfe selten auf freiem Platz stattfinden. Seine Schaftlänge erlaubt Distanzkontrolle auf schmalen Brücken, zwischen Pollern oder in knietiefem Wasser. Die Spitze ist langgezogen und leicht seitlich abgeflacht, damit sie auch bei nasser Kleidung und Lederlagen zuverlässig eindringt.

Ursprünglich wurde der Spieß gegen Schmuggler und Hafenräuber geführt. Später fand er seinen festen Platz in Patrouillen gegen [Nebelkriecher](/wissen/enzyklopaedie/monster-bestiarium/nebelkriecher), weil man mit ihm Gefahren aus der Dunstlinie heraus testen konnte, ohne den eigenen Stand zu verlieren. In der [Nebelmark](/wissen/enzyklopaedie/regionen/nebelmark) gilt er bis heute als „Waffe für Leute, die zurückkommen wollen“.

Die Schwäche liegt im Nahbereich. Wer den Träger bindet, neutralisiert viele Vorteile. Gute Speerführer trainieren deshalb den Übergang auf kurze Griffzonen und Seitenschläge.

### Spielrelevanz (Richtwerte)
- Typ: Waffe
- Angriff: 56 %
- Parade: 48 %
- Schaden pro Treffer: 14

Im Nebel gewinnt oft nicht der Mutigste, sondern der mit dem längeren Atem und der längeren Linie.
MD,
                'game_relevance' => [
                    'probe_hint' => 'Richtwerte: Angriff 56 %, Parade 48 %, Schaden 14. Stark in Distanzkontrolle, schwächer im Nahraum.',
                    'real_world_hint' => 'Für Real-World-Anfänger leicht erzählbar als pragmatische Hafen- oder Milizwaffe.',
                ],
            ],
            [
                'title' => 'Kettenklinge der Nerez-Häuser',
                'slug' => 'kettenklinge-der-nerez-haeuser',
                'excerpt' => 'Flexible Hiebwaffe für Duelle, Schuldvollstreckung und enge Stadtkämpfe.',
                'position' => 30,
                'content' => <<<'MD'
Die Kettenklinge der [Schattenhaeuser von Nerez](/wissen/enzyklopaedie/machtbloecke/schattenhaeuser-von-nerez) ist berüchtigt, weil sie wie ein kurzes Schwert beginnt und im Kampf in eine halbflexible Linie übergeht. Zwischen Griff und Klinge sitzen verstärkte Gelenksegmente, die abrupte Winkelwechsel erlauben. Für Außenstehende wirkt das wie Show. Für geübte Vollstrecker bedeutet es Kontrolle über unberechenbare Bewegungsräume.

Die Waffe wurde für enge Gassen von [Dornhafen](/wissen/enzyklopaedie/regionen/dornhafen-am-roten-delta) und Innenräume ohne Fluchtweg entwickelt. Dort kann sie hinter Schilden oder um Kanten geführt werden, wo starre Klingen an Grenzen stoßen. Der Preis ist hoch: Wer den Rhythmus verliert, öffnet die eigene Linie.

Viele Orden verurteilen die Kettenklinge als unredlich. Gerade deshalb lieben sie Schuldhäuser und Auftragsduellanten. Sie passt zu Kämpfern, die nicht den ersten Schlag suchen, sondern den zweiten im falschen Winkel.

### Spielrelevanz (Richtwerte)
- Typ: Waffe
- Angriff: 62 %
- Parade: 52 %
- Schaden pro Treffer: 11

Diese Klinge belohnt List und Timing – und bestraft jede Form von Stolz.
MD,
                'game_relevance' => [
                    'probe_hint' => 'Richtwerte: Angriff 62 %, Parade 52 %, Schaden 11. Taktische Waffe mit hoher Präzision.',
                    'real_world_hint' => 'Eher fortgeschrittene Ausrüstung; für Anfängerfiguren als seltener Fund statt Startauswahl geeignet.',
                ],
            ],
            [
                'title' => 'Runenschild „Morgenspalte“',
                'slug' => 'runenschild-morgenspalte',
                'excerpt' => 'Defensivschild mit eingelegten Basaltrunen aus den Passkriegen.',
                'position' => 40,
                'content' => <<<'MD'
„Morgenspalte“ ist die bekannteste Schildlinie aus den Werkhallen nahe [Obsidiantor von Kharas](/wissen/enzyklopaedie/regionen/obsidiantor-von-kharas). Der Schild besteht aus laminiertem Holz, Basaltfaser und einer dünnen Eisenhaut, in die kurze Schutzrunen eingraviert werden. Diese Runen sind nicht hochmagisch, sondern strukturgebend: Sie verteilen den Aufprall besser und halten das Material länger stabil.

Geführt wird Morgenspalte oft zusammen mit dem [Langschwert der Aschewacht](/wissen/enzyklopaedie/waffen-ruestungen-relikte/langschwert-der-aschewacht), besonders in Verteidigungslinien gegen [Schlackentrolle](/wissen/enzyklopaedie/monster-bestiarium/schlackentroll) oder Kavalleriereste. Die Schildkante ist verstärkt und kann offensiv eingesetzt werden, wenn der Gegner zu dicht drückt.

Morgenspalte ist schwerer als Standardrundschild-Modelle. Wer ihn führen will, braucht Schulterausdauer und klare Disziplin. In ungeordneten Gefechten wird das Gewicht schnell zur Belastung.

### Spielrelevanz (Richtwerte)
- Typ: Schildwaffe
- Angriff: 40 %
- Parade: 65 %
- Schaden pro Treffer: 6

Ein guter Schild rettet nicht nur den Träger, sondern die Linie hinter ihm.
MD,
                'game_relevance' => [
                    'probe_hint' => 'Richtwerte: Angriff 40 %, Parade 65 %, Schaden 6. Defensivfokus mit starker Linienkontrolle.',
                    'rs_hint' => 'Ergänzt hohe RS-Setups besonders gut, kann aber Mobilität in langen Märschen kosten.',
                ],
            ],
            [
                'title' => 'Basaltplattenpanzer der Kämme',
                'slug' => 'basaltplattenpanzer-der-kaemme',
                'excerpt' => 'Schwere Zwergenrüstung für Belagerungen, Passkämpfe und Standhalten unter Druck.',
                'position' => 50,
                'content' => <<<'MD'
Der Basaltplattenpanzer ist eine ikonische Rüstung der [Zwerge der Basaltkämme](/wissen/enzyklopaedie/voelker-spezies/zwerge-der-basaltkaemme). Er kombiniert mehrlagige Stahlsegmente mit basaltgehärteten Schulter- und Brustplatten. Das Ergebnis wirkt klobig, verteilt Trefferlast aber außergewöhnlich gut. In den Schanzen von Kharas heißt es: „Wer darin fällt, fällt spät.“

Entwickelt wurde der Panzer für Fronten gegen große Wuchtgegner wie [Basaltwyrme](/wissen/enzyklopaedie/monster-bestiarium/basaltwyrm) und improvisierte Rammböcke. Sein Hauptvorteil ist nicht Unverwundbarkeit, sondern Zeitgewinn für Formation und Gegenstoß. Der Nachteil liegt in Ausdauer und Lautstärke: Der Träger ist hörbar und verbrennt auf langen Märschen schneller Kraft.

Viele nicht-zwergische Söldner tragen vereinfachte Nachbauten, die deutlich billiger, aber auch instabiler sind. Ein echter Kamm-Panzer wird meist vererbt oder nach einem Letzten Schwur weitergereicht.

### Spielrelevanz (Richtwerte)
- Typ: Rüstung
- Rüstungsschutz (RS): 6
- Beweglichkeit: reduziert

Diese Rüstung macht niemanden schneller – nur schwerer zu töten.
MD,
                'game_relevance' => [
                    'rs_hint' => 'Richtwert: RS 6. Sehr hoher Schutz, dafür spürbare Mobilitätskosten.',
                    'le_hint' => 'Hoher RS reduziert LE-Verluste in längeren Frontszenen deutlich.',
                ],
            ],
            [
                'title' => 'Moorschuppenmantel',
                'slug' => 'moorschuppenmantel',
                'excerpt' => 'Leichte Feuchtrüstung aus Harzleder und Schuppenplatten der Sumpfjagd.',
                'position' => 60,
                'content' => <<<'MD'
Der Moorschuppenmantel ist eine praktische Rüstungslösung aus dem [Schweigefenn-Konföderat](/wissen/enzyklopaedie/regionen/schweigefenn-konfoederat). Er besteht aus geöltem Leder, Harzversiegelung und übereinander gesetzten Schuppenplatten, die Wasser ableiten und kleine Schnitte dämpfen. Anders als schwere Panzer bleibt der Mantel auch in Schlammfeldern relativ beweglich.

Viele [Moorwandler](/wissen/enzyklopaedie/voelker-spezies/moorwandler) kombinieren ihn mit Tuchmasken gegen [Blutmottenschwärme](/wissen/enzyklopaedie/monster-bestiarium/blutmottenschwarm). Die Rüstung schützt nicht vollständig vor Schwarmdruck, verhindert aber, dass jede Berührung sofort offene Wunden reißt.

In trockenen Regionen wird der Mantel oft unterschätzt. Tatsächlich bewährt er sich überall dort, wo Beweglichkeit und Tarnung wichtiger sind als starre Standfestigkeit. Wer ihn trägt, setzt auf Ausweichen, Terrain und frühe Wahrnehmung statt auf das Aushalten roher Treffer.

### Spielrelevanz (Richtwerte)
- Typ: Rüstung
- Rüstungsschutz (RS): 3
- Beweglichkeit: hoch

Der Mantel rettet keine Helden durch Härte, sondern durch den Raum, den er ihnen lässt.
MD,
                'game_relevance' => [
                    'rs_hint' => 'Richtwert: RS 3. Solider Schutz bei hoher Beweglichkeit.',
                    'probe_hint' => 'Passt zu Gewandtheit/Intuition-orientierten Figuren und taktischem Positionsspiel.',
                ],
            ],
            [
                'title' => 'Ascheleder des Letzten Schwurs',
                'slug' => 'ascheleder-des-letzten-schwurs',
                'excerpt' => 'Rituell versiegelte Mantelrüstung für Eidträger und Grenzrichter.',
                'position' => 70,
                'content' => <<<'MD'
Ascheleder ist eine seltene Mantelrüstung, die in liturgischen Werkstätten des [Ordens der Glutrichter](/wissen/enzyklopaedie/machtbloecke/orden-der-glutrichter) gefertigt wird. Das Material wirkt zunächst unspektakulär: dunkles Leder, matte Nähte, kein Schmuck. Erst bei Näherung von Rissphänomenen zeigt sich sein Wert. Dann werden die inneren Aschelinien warm und versteifen den Mantel an neuralgischen Stellen.

Getragen wird Ascheleder meist von Trägern, die mit [liturgischen Letztschwüren](/wissen/enzyklopaedie/magie-liturgie/liturgischer-letztschwur) oder instabilen Ritualorten arbeiten. In den Trümmerzonen von [Serath](/wissen/enzyklopaedie/regionen/truemmerkranz-von-serath) gilt es als Standard für Ermittler, die zwischen Glauben und Verbot balancieren müssen.

Die Rüstung ist leichter als Plattenpanzer, aber deutlich teurer als Feldleder. Ihr eigentlicher Preis ist ideologisch: Wer Ascheleder trägt, wird sofort einer Seite zugerechnet, selbst wenn er neutral bleiben will.

### Spielrelevanz (Richtwerte)
- Typ: Rüstung
- Rüstungsschutz (RS): 4
- Sonderprofil: stabil bei rituellem Umfeld

Ascheleder schützt den Körper – und bindet die Seele an Entscheidungen, die nicht mehr zurückgenommen werden.
MD,
                'game_relevance' => [
                    'rs_hint' => 'Richtwert: RS 4. Gute Balance aus Schutz und Mobilität.',
                    'ae_hint' => 'Kann erzählerisch Boni in ritualnahen Szenen begründen, ersetzt aber keine echte Magiebegabung.',
                ],
            ],
            [
                'title' => 'Relikt: Schwurfunke von Carron',
                'slug' => 'relikt-schwurfunke-von-carron',
                'excerpt' => 'Kleines Glutrelikt, das Eide verstärkt und Lügen im falschen Moment bricht.',
                'position' => 80,
                'content' => <<<'MD'
Der Schwurfunke von Carron ist ein daumengroßes Relikt aus schwarzem Metall, in dessen Kern ein roter Lichtpunkt pulsiert wie ein langsamer Herzschlag. Über seine Herkunft streiten Chronisten. Einige führen ihn auf frühe Gerichtskulte zurück, andere auf private Experimente aus den [Verlorenen Schulen von Iskand](/wissen/enzyklopaedie/magie-liturgie/verlorene-schulen-von-iskand).

Wird der Funke bei einem Eid in der Hand gehalten, verstärkt er Bindung und Konsequenz: klare Schwüre tragen weiter, halbe Wahrheiten zerreißen. Genau deshalb gilt er als gefährlich. In den Händen fanatischer Träger verwandelt er Verhandlung in Zwang. Bei [Schwurgeborenen](/wissen/enzyklopaedie/voelker-spezies/schwurgeborene) kann der Funke alte Narben aufreißen und ungewollte Erinnerungsbilder auslösen.

Der [Orden der Glutrichter](/wissen/enzyklopaedie/machtbloecke/orden-der-glutrichter) stuft den Besitz als kontrollpflichtig ein. Offiziell zum Schutz der Ordnung, inoffiziell auch aus Angst vor Machtverlust.

### Spielrelevanz (Richtwerte)
- Typ: Relikt
- Wirkung: Eid- und Wahrheitsdruck in sozialen Szenen
- Risiko: psychische Rückkopplung bei instabilen Trägern

Der Funke macht Worte schwerer – und jeden Bruch tödlich klar.
MD,
                'game_relevance' => [
                    'probe_hint' => 'Stark für GM-Proben auf Charisma/Mut bei Verhören, Schwüren und Loyalitätskonflikten.',
                    'ae_hint' => 'Relikt kann astrale Kosten auslösen, verleiht aber Nicht-Magiern keine volle Magiebahn.',
                ],
            ],
            [
                'title' => 'Relikt: Splitterkrone von Iskand',
                'slug' => 'relikt-splitterkrone-von-iskand',
                'excerpt' => 'Fragmentierte Denkerkrone, die Wissen schenkt und Identität zerfranst.',
                'position' => 90,
                'content' => <<<'MD'
Die Splitterkrone von Iskand besteht aus sieben schmalen Segmenten aus Glasmetall, die nicht auf dem Kopf liegen, sondern knapp darüber schweben, sobald das Relikt aktiviert wird. Ihre Schärfe richtet sich nicht gegen Fleisch, sondern gegen Unklarheit: Träger sehen Muster, Lücken und Widersprüche mit beunruhigender Präzision.

Der Preis ist hoch. Jede Nutzung drückt den Träger näher an das Phänomen der [Astralwunden und des Funkenzolls](/wissen/enzyklopaedie/magie-liturgie/astralwunden-und-funkenzoll). Erinnerungen werden brüchig, Gesichter vertauschen sich, und alte Schuld taucht in falschen Momenten auf. Aus diesem Grund wurde die Krone nach dem Fall von Iskand in Teile zerlegt und versteckt.

Mehrere Fraktionen suchen sie weiterhin, darunter Agenten der [Schattenhaeuser von Nerez](/wissen/enzyklopaedie/machtbloecke/schattenhaeuser-von-nerez), die strategisches Wissen über alles stellen. Wer die Krone vollständig zusammensetzt, gewinnt keinen Sieg – nur eine sehr scharfe Form von Einsamkeit.

### Spielrelevanz (Richtwerte)
- Typ: Relikt
- Wirkung: Wissens- und Analyseboost in kritischen Szenen
- Risiko: Erinnerungsschäden und Identitätsdruck

Die Krone gibt Antworten, bis der Träger vergisst, welche Frage ursprünglich seine war.
MD,
                'game_relevance' => [
                    'probe_hint' => 'Geeignet für Klugheit/Intuition-Lasttests mit starkem Nutzen und klaren Nebenfolgen.',
                    'ae_hint' => 'Hoher astraler Druck; kann AE-Reserven schnell aufbrauchen.',
                ],
            ],
            [
                'title' => 'Relikt: Blutanker der Tiefenpforte',
                'slug' => 'relikt-blutanker-der-tiefenpforte',
                'excerpt' => 'Schweres Kettenrelikt zur Stabilisierung von Risszonen mit tödlichem Gegenzoll.',
                'position' => 100,
                'content' => <<<'MD'
Der Blutanker ist ein kettenumwundenes Relikt aus dunklem Erz, groß wie ein Unterarm und mit eingelassenen Markierungen alter Ritussprachen. Er wurde entwickelt, um instabile [Blutpfortenrituale](/wissen/enzyklopaedie/magie-liturgie/blutpfortenrituale) für kurze Zeit zu „erden“. In der Praxis bedeutet das: weniger chaotische Ausbrüche, dafür ein konzentrierter Preis, der auf die Trägergruppe fällt.

Frühe Einsätze in den Ruinen von [Serath](/wissen/enzyklopaedie/regionen/truemmerkranz-von-serath) reduzierten zivile Verluste, forderten aber ganze Einsatztrupps durch Nachhallfieber und Blutstürze. Deshalb führen heutige Ritusgruppen den Anker nur noch unter strikten Protokollen: klare Rollen, dokumentierte Opfergrenzen und sofortiger Rückzug nach Stabilisierung.

Im Schwarzmarkt von [Dornhafen](/wissen/enzyklopaedie/regionen/dornhafen-am-roten-delta) kursieren Fälschungen, die ähnlich aussehen, aber beim Einsatz unkontrolliert zerbrechen. Das Ergebnis ist oft schlimmer als ohne Anker.

### Spielrelevanz (Richtwerte)
- Typ: Relikt
- Wirkung: kurzzeitige Ritualstabilisierung
- Risiko: direkte LE/AE-Folgekosten für Beteiligte

Der Blutanker hält das Tor nur lange genug offen, damit jemand entscheidet, was geopfert wird.
MD,
                'game_relevance' => [
                    'le_hint' => 'Kann hohe unmittelbare LE-Kosten als Preis für Ritualstabilisierung auslösen.',
                    'ae_hint' => 'Zusätzliche AE-Belastung bei ritualnahen Aktionen wahrscheinlich.',
                    'probe_hint' => 'Typische GM-Proben: Klugheit/Mut/Konstitution in Eskalationssequenzen.',
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
            ->where('slug', 'waffen-ruestungen-relikte')
            ->value('id');

        if (! is_numeric($categoryId)) {
            return;
        }

        DB::table('encyclopedia_entries')
            ->where('encyclopedia_category_id', (int) $categoryId)
            ->whereIn('slug', [
                'langschwert-der-aschewacht',
                'nebelspiess-von-dornhafen',
                'kettenklinge-der-nerez-haeuser',
                'runenschild-morgenspalte',
                'basaltplattenpanzer-der-kaemme',
                'moorschuppenmantel',
                'ascheleder-des-letzten-schwurs',
                'relikt-schwurfunke-von-carron',
                'relikt-splitterkrone-von-iskand',
                'relikt-blutanker-der-tiefenpforte',
            ])
            ->delete();

        DB::table('encyclopedia_categories')
            ->where('id', (int) $categoryId)
            ->delete();
    }
};
