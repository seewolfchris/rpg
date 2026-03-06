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

        $categoryId = DB::table('encyclopedia_categories')
            ->where('slug', 'regionen')
            ->value('id');

        if (! is_numeric($categoryId)) {
            return;
        }

        $entries = [
            [
                'title' => 'Dornhafen am Roten Delta',
                'slug' => 'dornhafen-am-roten-delta',
                'excerpt' => 'Hafenstadt zwischen Schlamm, Schuldbriefen und nächtlichen Klingen.',
                'position' => 30,
                'content' => <<<'MD'
Dornhafen liegt dort, wo der Fluss in ein trübes Delta aus rostigem Schlamm und zerrissenen Stegen ausläuft. Tagsüber ist die Stadt ein lärmender Markt aus Salzfisch, Alchemieresten und gestohlenen Bannzeichen. Nachts gehört sie den Schuldhändlern, Messerträgern und den stillen Booten ohne Flagge. Wer hier anlegt, zahlt dreifach: Zoll, Schutzgeld und das Schweigen über das, was er gesehen hat.

Die [Schattenhaeuser von Nerez](/wissen/enzyklopaedie/machtbloecke/schattenhaeuser-von-nerez) führen in Dornhafen halboffene Kontore, deren Bilanzen aus Blut, Korn und Gerüchten bestehen. Gleichzeitig beansprucht der [Orden der Glutrichter](/wissen/enzyklopaedie/machtbloecke/orden-der-glutrichter) die Gerichtshoheit über alle ritusnahen Delikte. Zwischen beiden Machtblöcken überleben die Bürger mit Doppelrollen: tagsüber Lastträger, nachts Informanten.

Außerhalb der Mauern beginnen Schilfzonen, in denen [Nebelkriecher](/wissen/enzyklopaedie/monster-bestiarium/nebelkriecher) Stimmen aus dem Dunst reißen. Viele Karawanen aus der [Nebelmark](/wissen/enzyklopaedie/regionen/nebelmark) verlieren dort Wachen, bevor der erste Handel abgeschlossen ist.

Dornhafen belohnt Entschlossene und frisst Zögernde bei lebendigem Leib.
MD,
                'game_relevance' => [
                    'probe_hint' => 'Starke Bühne für soziale und investigative GM-Proben auf Charisma, Intuition und Klugheit.',
                    'real_world_hint' => 'Real-World-Anfänger können hier glaubhaft als Fremde ohne Ortskenntnis einsteigen.',
                ],
            ],
            [
                'title' => 'Obsidiantor von Kharas',
                'slug' => 'obsidiantor-von-kharas',
                'excerpt' => 'Basaltfestung über den Salzpässen, kalt geführt und gnadenlos besteuert.',
                'position' => 40,
                'content' => <<<'MD'
Das Obsidiantor von Kharas ist keine Stadt im klassischen Sinn, sondern ein gestufter Festungskomplex aus schwarzem Stein, der den wichtigsten Salzpass des Nordens beherrscht. Jeder Wagen, jede Trage und jede Pilgergruppe muss durch seine Zählschächte. Die Zöllner schreiben nicht nur Waren, sondern Namen, Wappenreste und alte Schwüre in ihre Register. Wer einmal eingetragen ist, bleibt für Jahre verfolgbar.

Verwaltet wird Kharas von einem Bündnis aus [Zwergen der Basaltkämme](/wissen/enzyklopaedie/voelker-spezies/zwerge-der-basaltkaemme) und menschlichen Kriegsschreibern. Die Zusammenarbeit gilt als stabil, weil beide Seiten Ordnung höher bewerten als Eitelkeit. Konflikte entstehen erst, wenn [Kettenerben](/wissen/enzyklopaedie/voelker-spezies/kettenerben) mit alten Dienstrechten auftauchen und Sonderwege verlangen.

Unter den Fundamenten verlaufen verlassene Minenarme, in denen [Grubenhäscher](/wissen/enzyklopaedie/monster-bestiarium/grubenhaescher) patrouillieren wie lebende Fallen. Deshalb reist niemand nachts ohne Leinenordnung und Signalschläge.

Kharas ist kein Ort der Hoffnung, sondern ein Tor, das nur für jene offensteht, die den Preis tragen können.
MD,
                'game_relevance' => [
                    'probe_hint' => 'Typische GM-Proben: Klugheit (Zoll/Urkunden), Mut (Passdurchbruch), Körperkraft (Gebirgsmarsch).',
                    'rs_hint' => 'Frontlastige Szenen am Pass begünstigen robustere Rüstungs-Setups.',
                ],
            ],
            [
                'title' => 'Trümmerkranz von Serath',
                'slug' => 'truemmerkranz-von-serath',
                'excerpt' => 'Ring aus eingestürzten Sonnenkronen-Bastionen mit unruhiger Nachtmagie.',
                'position' => 50,
                'content' => <<<'MD'
Serath war einst eine stolze Provinzstadt der [Sonnenkronen](/wissen/enzyklopaedie/zeitalter/zeitalter-der-sonnenkronen). Heute bleibt davon ein Trümmerkranz: sieben ruinierte Bastionen, durch Schutthügel und verkohlte Plätze verbunden. Zwischen den Ruinen leben kleinere Gemeinschaften aus Schrotthändlern, Pilgern und ehemaligen Soldaten, die aus dem [Aschenfall](/wissen/enzyklopaedie/zeitalter/der-aschenfall) nie wirklich zurückfanden.

Die Nächte in Serath sind berüchtigt. In den tieferen Hofanlagen öffnen sich gelegentlich Risszonen, die den Charakter eines instabilen [Blutpfortenrituals](/wissen/enzyklopaedie/magie-liturgie/blutpfortenrituale) tragen: flackernde Schatten, fehlerhafte Echos, kurzzeitige Orientierungslosigkeit. Manche Viertel bilden dadurch temporäre Nullzonen, die Magie verschlucken und normale Kommandostrukturen zersetzen.

Jäger berichten außerdem von [Rußbanshees](/wissen/enzyklopaedie/monster-bestiarium/russbanshee), die in alten Kapellengassen heulen, sobald größere Gruppen mit offenen Fackeln einmarschieren.

Serath ist ein Ort, an dem jeder Schritt Geschichte berührt – und Geschichte mit Zähnen zurückbeißt.
MD,
                'game_relevance' => [
                    'ae_hint' => 'Magieeinsatz kann lokal instabil sein; temporäre Nullzonen sind als Szeneneffekt plausibel.',
                    'probe_hint' => 'Typische GM-Proben: Intuition/Konstitution gegen Desorientierung, Mut bei Nachhallphänomenen.',
                ],
            ],
            [
                'title' => 'Schweigefenn-Konföderat',
                'slug' => 'schweigefenn-konfoederat',
                'excerpt' => 'Lose Moorbünde aus Pfahldörfern, Harztempeln und stillen Jagdpfaden.',
                'position' => 60,
                'content' => <<<'MD'
Das Schweigefenn ist kein geeintes Reich, sondern ein Bündnis aus Moorclans, die nur in Krisenjahren geschlossen auftreten. Ihre Pfahldörfer liegen auf wandernden Torfinseln, verbunden durch Seile, Flachboote und codierte Rauchzeichen. Ein fremder Blick erkennt nur Nebel und Wasser. Ein Einheimischer sieht darin Wege, Fallen und Vorratslinien.

Politisch stützt sich das Konföderat auf Sprecherzirkel, in denen [Moorwandler](/wissen/enzyklopaedie/voelker-spezies/moorwandler), freie Jäger und vereinzelte [Rabenblütige](/wissen/enzyklopaedie/voelker-spezies/rabenbluetige) verhandeln. Entscheidungen gelten nie ewig, sondern „bis zur nächsten Ebbe aus Blut und Regen“. Das macht Bündnisse flexibel, aber für Außenstehende schwer planbar.

Die größte Gefahr sind saisonale [Blutmottenschwärme](/wissen/enzyklopaedie/monster-bestiarium/blutmottenschwarm), die ganze Dörfer zur Evakuierung zwingen. Gleichzeitig bewahren gerade diese Krisen das Fenn vor Eroberung: Fremde Heere verlieren dort Versorgung, Ordnung und Geduld.

Im Schweigefenn spricht man leise, weil laute Männer meist nur kurz überleben.
MD,
                'game_relevance' => [
                    'probe_hint' => 'Ideal für Survival-Szenen mit GM-Proben auf Intuition, Gewandtheit und Konstitution.',
                    'real_world_hint' => 'Real-World-Anfänger wirken hier glaubhaft als kulturelle Außenseiter mit Lernkurve.',
                ],
            ],
            [
                'title' => 'Vesperwall und die Salzpässe',
                'slug' => 'vesperwall-und-die-salzpaesse',
                'excerpt' => 'Militärischer Grenzriegel, der Karawanen schützt und zugleich ausblutet.',
                'position' => 70,
                'content' => <<<'MD'
Vesperwall bezeichnet eine Kette aus Wachtürmen, Schanzdörfern und alten Signalfeuern entlang der östlichen Salzpässe. Der Name klingt wie eine Stadt, tatsächlich ist es ein militärisches Band aus dutzenden kleinen Standorten. Jeder Abschnitt wird von einer anderen Truppe gehalten: Söldner, Restlegionen, lokale Milizen oder angeheuerte [Menschen der Aschepfade](/wissen/enzyklopaedie/voelker-spezies/menschen-der-aschepfade).

Die zentrale Aufgabe ist der Schutz der Salzrouten Richtung Süden. In schlechten Wintern wird daraus ein Stellungskrieg gegen Räuberzüge, ausgehungerte Deserteure und wandernde Bestien. Besonders gefürchtet sind [Aschenwulf-Rudel](/wissen/enzyklopaedie/monster-bestiarium/aschenwulf) sowie vereinzelte [Knochengreife](/wissen/enzyklopaedie/monster-bestiarium/knochengreif), die Nachschublinien zerlegen.

Religiös herrscht am Wall ein kalter Pragmatismus: Priester sprechen kurze Liturgien für Disziplin und Ruhe, vermeiden aber große Eide, seit mehrere Außenposten nach misslungenen [liturgischen Letztschwüren](/wissen/enzyklopaedie/magie-liturgie/liturgischer-letztschwur) kollabierten.

Vesperwall hält das Land zusammen, indem es jeden Tag ein Stück von sich selbst verliert.
MD,
                'game_relevance' => [
                    'le_hint' => 'Lange Marsch- und Belagerungsszenen erzeugen verlässlichen LE-Druck über Zeit.',
                    'probe_hint' => 'Typische GM-Proben: Mut (Frontkontakt), Klugheit (Versorgung), Körperkraft (Marschlast).',
                    'rs_hint' => 'Rüstungsschutz ist an der Passfront oft relevanter als hohe Beweglichkeit.',
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
            ->where('slug', 'regionen')
            ->value('id');

        if (! is_numeric($categoryId)) {
            return;
        }

        DB::table('encyclopedia_entries')
            ->where('encyclopedia_category_id', (int) $categoryId)
            ->whereIn('slug', [
                'dornhafen-am-roten-delta',
                'obsidiantor-von-kharas',
                'truemmerkranz-von-serath',
                'schweigefenn-konfoederat',
                'vesperwall-und-die-salzpaesse',
            ])
            ->delete();
    }
};
