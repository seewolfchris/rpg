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
                'name' => 'Heldenarchetypen & Berufungen',
                'slug' => 'heldenarchetypen-berufungen',
                'summary' => 'Rollenbilder für Helden in Vhal\'Tor: düster, praxisnah und an die Charaktererschaffung gekoppelt.',
                'position' => 90,
                'is_public' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['slug'], ['name', 'summary', 'position', 'is_public', 'updated_at']);

        $categoryId = DB::table('encyclopedia_categories')
            ->where('slug', 'heldenarchetypen-berufungen')
            ->value('id');

        if (! is_numeric($categoryId)) {
            return;
        }

        $entries = [
            [
                'title' => 'Aschemagier der gebrochenen Sterne',
                'slug' => 'aschemagier-der-gebrochenen-sterne',
                'excerpt' => 'Wissensjäger zwischen Verbot und Notwendigkeit, deren Macht immer einen Preis fordert.',
                'position' => 10,
                'content' => <<<'MD'
Aschemagier sind keine leuchtenden Wunderwirker, sondern Überlebende mit verbrannten Fingern, zerrissenen Notizbüchern und einem schlechten Verhältnis zu ruhigem Schlaf. Sie lernen früh, dass jede Formel in Vhal'Tor eine Rechnung ist. Mal zahlt man mit Müdigkeit, mal mit Erinnerung, mal mit einem Namen, der plötzlich nicht mehr im eigenen Mund liegt. Wer sich in dieser Kunst halten will, muss neben Disziplin auch Demut lernen.

Viele Aschemagier stammen aus Restkreisen der [Verlorenen Schulen von Iskand](/wissen/enzyklopaedie/magie-liturgie/verlorene-schulen-von-iskand) oder wurden von älteren Wandermeistern aufgesammelt, bevor die [Astralwunden und der Funkenzoll](/wissen/enzyklopaedie/magie-liturgie/astralwunden-und-funkenzoll) sie vollständig zerlegt haben. In den Grenzlanden gelten sie zugleich als Hoffnung und Risiko: nützlich im Notfall, gefährlich im Dauerzustand.

Aschemagier sind oft die Ersten, die Rissphänomene deuten können, und die Letzten, die im Streit gefragt werden. Ihr Wissen rettet Gruppen, macht sie aber selten beliebt. Wer ihre Hilfe will, muss akzeptieren, dass manche Türen nach dem Öffnen nicht mehr geschlossen werden können.

Im Dunkel der Ruinen ist ihr Licht verlässlich – und niemals kostenlos.
MD,
                'game_relevance' => [
                    'ae_hint' => 'Direkter Bezug zur Berufung Magier: hohe AE-Relevanz, aber stets mit Risiko und Ressourcenverwaltung.',
                    'probe_hint' => 'Typische GM-Proben: Klugheit/Intuition für Deutung, Mut bei Kontrollverlust und Ritualstress.',
                    'real_world_hint' => 'Real-World-Anfänger starten nicht als Magier; magische Entwicklung muss ingame begründet werden.',
                ],
            ],
            [
                'title' => 'Schwurritter der Grenzmark',
                'slug' => 'schwurritter-der-grenzmark',
                'excerpt' => 'Eidgebundene Frontführer, die Linie halten, auch wenn das Land längst brennt.',
                'position' => 20,
                'content' => <<<'MD'
Schwurritter sind die harten Scharniere zwischen Ordnung und Zusammenbruch. Wo Grenzlinien aufbrechen, Straßen veröden und Vorräte knapp werden, stehen sie mit wenigen Leuten an den falschen Orten zur falschen Zeit – und halten trotzdem. Ihre Rolle ist nicht heroischer Glanz, sondern Verantwortung in langen Nächten, wenn jeder Fehler sofort Menschen kostet.

Viele Schwurritter dienen in Abschnitten von [Vesperwall und den Salzpässen](/wissen/enzyklopaedie/regionen/vesperwall-und-die-salzpaesse), ausgestattet mit einem [Langschwert der Aschewacht](/wissen/enzyklopaedie/waffen-ruestungen-relikte/langschwert-der-aschewacht) oder schwerem Schildgerät. Sie führen in klaren Kommandos, weil Chaos im Feld tödlicher ist als jeder Feind. Der Preis dieser Disziplin ist hoch: Private Bindungen brechen oft zuerst, wenn der nächste Marschbefehl kommt.

In religiösen Krisen geraten Schwurritter regelmäßig zwischen weltliche Pflicht und liturgischen Druck. Manche haben den [liturgischen Letztschwur](/wissen/enzyklopaedie/magie-liturgie/liturgischer-letztschwur) gesprochen und tragen dessen Folgen als Narbe in Stimme, Blick oder Schlaf.

Ein Schwurritter wird selten geliebt, aber oft vermisst, sobald die Linie fällt.
MD,
                'game_relevance' => [
                    'le_hint' => 'Direkter Bezug zur Berufung Ritter: hohe Frontbelastung und LE-Druck über längere Szenen.',
                    'probe_hint' => 'Typische GM-Proben: Mut/Körperkraft/Gewandtheit für Halten, Vorstoß und geordnete Rückzüge.',
                    'rs_hint' => 'Skaliert stark mit solider Rüstung und defensiver Waffenführung.',
                ],
            ],
            [
                'title' => 'Freipfad-Abenteurer',
                'slug' => 'freipfad-abenteurer',
                'excerpt' => 'Grenzgänger ohne festen Banner, schnell im Kopf und schneller auf den Beinen.',
                'position' => 30,
                'content' => <<<'MD'
Freipfad-Abenteurer leben vom Übergang: zwischen Stadt und Wildnis, Vertrag und Verrat, Hoffnung und nacktem Überleben. Sie haben selten formale Ausbildung, dafür ein feines Gespür für Routen, Risiken und Menschen, die unter Druck brechen könnten. In Vhal'Tor sind sie oft die ersten, die in unkartiertes Gebiet gehen – und die letzten, die eine Rückkehr noch glaubhaft schildern können.

Viele kommen aus den Reihen der [Menschen der Aschepfade](/wissen/enzyklopaedie/voelker-spezies/menschen-der-aschepfade), einige aus den Schattenmärkten von [Dornhafen](/wissen/enzyklopaedie/regionen/dornhafen-am-roten-delta). Sie handeln mit Wegwissen, Beweismitteln, Reliktfunden oder schlicht mit ihrer Bereitschaft, als Vorhut in den Nebel zu gehen. Der Beruf ist frei, aber nie sicher.

Im Kampf vermeiden Freipfad-Leute starre Fronten. Sie nutzen Gelände, Tempo und den Moment, in dem der Gegner einen Fehler für Zufall hält. Gegen Rudeljäger wie den [Aschenwulf](/wissen/enzyklopaedie/monster-bestiarium/aschenwulf) überleben sie nur, wenn sie Disziplin nicht mit Starrheit verwechseln.

Ihre größte Stärke ist Anpassung – und ihre größte Gefahr die Illusion, immer noch einen Ausweg zu haben.
MD,
                'game_relevance' => [
                    'probe_hint' => 'Direkter Bezug zur Berufung Abenteurer: Intuition/Gewandtheit/Mut als typische Kernproben.',
                    'real_world_hint' => 'Sehr guter Einstieg für Real-World-Figuren, die Vhal\'Tor schrittweise entdecken.',
                ],
            ],
            [
                'title' => 'Glutgeistliche der letzten Altäre',
                'slug' => 'glutgeistliche-der-letzten-altaere',
                'excerpt' => 'Seelsorger, Richter und Brandwächter in einer Welt, die kaum noch an Erlösung glaubt.',
                'position' => 40,
                'content' => <<<'MD'
Glutgeistliche sind weder reine Priester noch reine Funktionäre. Sie tragen Liturgie in Orte, an denen Ordnung bereits verbrannt ist: Feldlazarette, Flüchtlingskolonnen, belagerte Dörfer und zerbrochene Gerichtshallen. Ihr Auftrag ist doppelt: Trost spenden, wo Trost möglich ist, und Grenze ziehen, wo Verbotenes die Gemeinschaft zu verschlingen droht.

Viele stehen unter Einfluss des [Ordens der Glutrichter](/wissen/enzyklopaedie/machtbloecke/orden-der-glutrichter), einige arbeiten bewusst außerhalb offizieller Strukturen. In beiden Fällen bleibt ihre Position gefährlich. Wer Hoffnung predigt, wird schnell zum Ziel derer, die Kontrolle durch Angst sichern wollen. Wer zu hart urteilt, verliert die Menschen, die er schützen sollte.

Glutgeistliche tragen oft [Ascheleder des Letzten Schwurs](/wissen/enzyklopaedie/waffen-ruestungen-relikte/ascheleder-des-letzten-schwurs) und kennen die Folgen des [liturgischen Letztschwurs](/wissen/enzyklopaedie/magie-liturgie/liturgischer-letztschwur) aus nächster Nähe. Jeder Ritus kann retten – oder etwas zerbrechen, das später nicht mehr heilt.

Ihr Glaube ist selten ungebrochen. Aber gerade in den Rissen bleibt er manchmal tragfähig genug, um andere durch die Nacht zu führen.
MD,
                'game_relevance' => [
                    'ae_hint' => 'Direkter Bezug zur Berufung Geistlicher: mögliche AE-Nutzung über Liturgie und spirituelle Praxis.',
                    'probe_hint' => 'Typische GM-Proben: Charisma/Mut/Klugheit bei Vermittlung, Exorzismus und Eidkonflikten.',
                ],
            ],
            [
                'title' => 'Wundärzte des Rußbunds',
                'slug' => 'wundaerzte-des-russbunds',
                'excerpt' => 'Heiler, die zwischen Messer, Kräuterwissen und moralischer Schuld arbeiten.',
                'position' => 50,
                'content' => <<<'MD'
Wundärzte des Rußbunds operieren dort, wo klassische Tempelmedizin längst aufgegeben hat: in Seuchenhöfen, Moorlagern, Passschanzen und improvisierten Lazaretten hinter brennenden Linien. Ihre Kunst ist pragmatisch und häufig brutal. Sie retten Leben nicht durch Reinheit, sondern durch Timing, Nerven und die Bereitschaft, schnelle Entscheidungen mit lebenslangen Folgen zu tragen.

Viele Rußbund-Heiler rekrutieren sich aus Mischmilieus: ehemalige Feldscherer, Kräuterkundige aus dem [Schweigefenn-Konföderat](/wissen/enzyklopaedie/regionen/schweigefenn-konfoederat), dissidente Liturgie-Schüler und ausgeschlossene Alchemisten. Gemeinsam ist ihnen eine nüchterne Ethik: erst stabilisieren, dann urteilen. Gegen [Blutmottenschwärme](/wissen/enzyklopaedie/monster-bestiarium/blutmottenschwarm) und Wundfieber haben sie Protokolle entwickelt, die selbst Militärkapläne übernehmen.

In [Nullfeldern der Asche](/wissen/enzyklopaedie/magie-liturgie/nullfelder-der-asche) sind sie besonders gefragt, weil dort viele magische Hilfsmittel ausfallen. Dann zählen Hände, Material und Erfahrung mehr als jedes Gebet.

Der Rußbund kennt keine Heiligen – nur Menschen, die nach der Schicht noch zittern und am nächsten Morgen trotzdem wieder anfangen.
MD,
                'game_relevance' => [
                    'ae_hint' => 'Direkter Bezug zur Berufung Heiler: kann bei bestimmten Konzepten begrenzte AE-Nutzung enthalten, muss aber nicht.',
                    'probe_hint' => 'Typische GM-Proben: Klugheit/Intuition/Konstitution bei Notversorgung, Triage und Seuchenabwehr.',
                    'le_hint' => 'Heiler-Szenen bieten starke narrative Hebel zur LE-Stabilisierung außerhalb von Kämpfen.',
                ],
            ],
            [
                'title' => 'Splittergelehrte und Feldwissenschaftler',
                'slug' => 'splittergelehrte-und-feldwissenschaftler',
                'excerpt' => 'Analytiker des Verfalls, die Wahrheit zwischen Schutt, Blut und Datenresten sammeln.',
                'position' => 60,
                'content' => <<<'MD'
Splittergelehrte sind Wissenschaftler unter Kriegsbedingungen. Sie sammeln Proben aus Ruinen, katalogisieren Rissmuster, vergleichen Chroniken und bauen aus Fragmenten belastbare Modelle. Ihre Arbeit wirkt trocken, entscheidet aber oft über Leben und Tod: Welche Route ist stabil, welches Relikt ist benutzbar, welche Krankheit breitet sich als Nächstes aus?

Viele orientieren sich an Traditionen der [Verlorenen Schulen von Iskand](/wissen/enzyklopaedie/magie-liturgie/verlorene-schulen-von-iskand), ohne deren Idealismus zu übernehmen. In der Gegenwart zählt nicht reine Theorie, sondern angewandtes Überleben. Deshalb begleiten Feldwissenschaftler häufig Trupps in [Trümmerzonen von Serath](/wissen/enzyklopaedie/regionen/truemmerkranz-von-serath) oder entlang von Ritualschäden nach [Blutpfortenritualen](/wissen/enzyklopaedie/magie-liturgie/blutpfortenrituale).

Ihr Ruf ist ambivalent. Militärs brauchen ihre Berichte, misstrauen aber ihren Warnungen. Fanatiker halten sie für ketzerisch, weil Zahlen und Beobachtung Dogmen widersprechen können. Doch ohne ihre Arbeit würden viele Gruppen blind in dieselben Fehler laufen.

Splittergelehrte retten selten durch Schwert oder Zauber – sie retten, indem sie falsche Gewissheiten rechtzeitig zerlegen.
MD,
                'game_relevance' => [
                    'probe_hint' => 'Direkter Bezug zur Berufung Wissenschaftler: Klugheit/Intuition dominieren, oft mit hoher Informationswirkung.',
                    'ae_hint' => 'Keine automatische Magierolle; auch wissenschaftliche Charaktere können komplett ohne AE gespielt werden.',
                ],
            ],
            [
                'title' => 'Schattenklingen von Nerez',
                'slug' => 'schattenklingen-von-nerez',
                'excerpt' => 'Diebe, Infiltratoren und Schuldeintreiber der urbanen Nachtökonomie.',
                'position' => 70,
                'content' => <<<'MD'
Schattenklingen sind keine romantischen Räuber, sondern präzise Handwerker der Unsichtbarkeit. Sie öffnen Türen, beschaffen Beweise, entfernen Lasten aus den falschen Händen und verlassen den Ort, bevor jemand begreift, dass etwas fehlt. In den Machtzonen der [Schattenhaeuser von Nerez](/wissen/enzyklopaedie/machtbloecke/schattenhaeuser-von-nerez) gelten sie als notwendiges Instrument, offiziell geleugnet und intern hochbezahlt.

Ausgebildet wird in kleinen Zellen, oft über Jahre. Bewegung, Beobachtung, Stimmkontrolle und das Lesen von Gewohnheiten zählen mehr als rohe Gewalt. Als Symbolwaffe gilt die [Kettenklinge der Nerez-Häuser](/wissen/enzyklopaedie/waffen-ruestungen-relikte/kettenklinge-der-nerez-haeuser), doch die meisten Einsätze werden gewonnen, ohne dass Stahl sichtbar wird.

Schattenklingen überleben durch Disziplin: kein unnötiges Töten, keine improvisierten Heldentaten, keine persönliche Rache während eines Auftrags. Wer diese Regeln bricht, endet in den Archiven der eigenen Auftraggeber.

Sie nehmen, was andere verstecken, und hinterlassen nur die leise Ahnung, dass Vertrauen in Vhal'Tor nie eine harte Währung war.
MD,
                'game_relevance' => [
                    'probe_hint' => 'Direkter Bezug zur Berufung Dieb: Gewandtheit/Intuition/Charisma für Infiltration, Täuschung und Rückzug.',
                    'rs_hint' => 'Leichte Rüstung mit hoher Beweglichkeit ist meist effizienter als hohe RS-Werte.',
                ],
            ],
            [
                'title' => 'Krieger der Salzpässe',
                'slug' => 'krieger-der-salzpaesse',
                'excerpt' => 'Harte Frontsoldaten für Druckräume, Engpässe und den Kampf gegen schwere Bedrohungen.',
                'position' => 80,
                'content' => <<<'MD'
Krieger der Salzpässe sind das rohe Rückgrat vieler Grenzverbände. Ihr Alltag besteht aus Marsch, Schanzarbeit, kurzen Schlafphasen und Kämpfen, bei denen Fehler in Sekunden bestraft werden. Anders als Schwurritter tragen sie meist keine symbolische Leitrolle. Sie sind der Hammer der Linie: präzise, belastbar, austauschbar – zumindest auf dem Papier.

Ihre Ausbildung ist körperlich und repetitiv. Schläge unter Last, Schildwechsel, Formation unter Beschuss, kontrollierter Rückzug in schlechtem Gelände. Standardausrüstung variiert, doch Kombinationen aus [Runenschild „Morgenspalte“](/wissen/enzyklopaedie/waffen-ruestungen-relikte/runenschild-morgenspalte) und schweren Schutzprofilen wie dem [Basaltplattenpanzer der Kämme](/wissen/enzyklopaedie/waffen-ruestungen-relikte/basaltplattenpanzer-der-kaemme) sind häufig.

In Kämpfen gegen Großbedrohungen wie [Schlackentrolle](/wissen/enzyklopaedie/monster-bestiarium/schlackentroll) oder Belagerungsdurchbrüche zählen Krieger weniger über Individualstil als über Nervenstabilität. Wer die Formation hält, lebt. Wer ausschert, wird meist zur Warnung.

Sie werden selten besungen, aber ohne sie wäre der Pass schon lange offen für alles, was kriecht, brüllt und frisst.
MD,
                'game_relevance' => [
                    'le_hint' => 'Direkter Bezug zur Berufung Krieger: hohe LE-Belastung in Frontszenen, dafür starke Durchhalteprofile.',
                    'probe_hint' => 'Typische GM-Proben: Körperkraft/Mut/Gewandtheit in Druck- und Engraumsituationen.',
                    'rs_hint' => 'Profitiert überdurchschnittlich von hohem RS und defensiver Paradeführung.',
                ],
            ],
            [
                'title' => 'Barden der Narbenchronik',
                'slug' => 'barden-der-narbenchronik',
                'excerpt' => 'Stimmenführer, Erinnerungswächter und subtile Manipulatoren kollektiver Wahrheit.',
                'position' => 90,
                'content' => <<<'MD'
Barden der Narbenchronik sind keine Tavernenunterhalter, sondern Archivare in menschlicher Form. Sie sammeln Berichte über Schlachten, Eidbruch, Hungerjahre und verlorene Häuser, fassen sie in Lieder oder Sprechformeln und tragen diese durch Lager, Märkte und Gerichtshöfe. In einer Welt voller Propaganda ist ihr Einfluss gewaltig: Wer die Erzählung kontrolliert, kontrolliert Loyalität.

Viele Barden arbeiten unabhängig, einige im Auftrag von Stadtbünden oder versteckten Fraktionen. Mit Relikten wie dem [Schwurfunken von Carron](/wissen/enzyklopaedie/waffen-ruestungen-relikte/relikt-schwurfunke-von-carron) gehen sie vorsichtig um, weil Wahrheitsdruck schnell in Fanatismus kippen kann. In Ruinen mit [Rußbanshee-Nachhall](/wissen/enzyklopaedie/monster-bestiarium/russbanshee) dienen sie oft als psychische Anker für Trupps, die sonst im Lärm des Schreckens zerfallen.

Ein guter Barde heilt keine Wunden wie ein Rußbund-Arzt und hält keine Linie wie ein Krieger. Aber er kann einer Gruppe den Grund zurückgeben, überhaupt weiterzugehen.

Wenn alles brennt, bleibt manchmal nur die Stimme, die Namen noch richtig ausspricht.
MD,
                'game_relevance' => [
                    'probe_hint' => 'Direkter Bezug zur Berufung Barde: Charisma/Intuition zentral für Moral, Verhandlung und Szenenlenkung.',
                    'real_world_hint' => 'Für Real-World-Charaktere gut als soziale Brückenrolle zwischen Fremdheit und Gruppenkohäsion.',
                ],
            ],
            [
                'title' => 'Jäger der Nebelpfade',
                'slug' => 'jaeger-der-nebelpfade',
                'excerpt' => 'Fährtenleser und Wildhüter für Zonen, in denen selbst Karten nicht lange wahr bleiben.',
                'position' => 100,
                'content' => <<<'MD'
Jäger der Nebelpfade sind Spezialisten für Übergangsräume: Moorgrenzen, Schuttränder, Waldkorridore und alle Orte, an denen Sicht, Geräusch und Geruch gegeneinander arbeiten. Sie lesen nicht nur Spuren im Boden, sondern auch Windwechsel, Tierstille und das Verhalten von Begleitern unter Stress. Gute Jäger erkennen Gefahr oft Minuten früher als der Rest der Gruppe.

In Regionen wie dem [Schweigefenn-Konföderat](/wissen/enzyklopaedie/regionen/schweigefenn-konfoederat) oder den Randzonen der [Aschelande](/wissen/enzyklopaedie/regionen/aschelande) führen sie Karawanen durch Gebiete, die ohne ortskundige Begleitung als Selbstmord gelten. Ihre natürlichen Gegenspieler sind Rudeljäger und Tarnbestien wie [Aschenwölfe](/wissen/enzyklopaedie/monster-bestiarium/aschenwulf) oder der schnelle [Splitterhirsch](/wissen/enzyklopaedie/monster-bestiarium/splitterhirsch).

Jäger gewinnen selten durch rohe Gewalt. Sie gewinnen durch Vorbereitung: richtige Route, richtige Stunde, richtiges Tempo. Wer ihnen nicht zuhört, läuft meistens in das Problem hinein, das der Jäger längst gesehen hat.

Ihre größte Kunst ist nicht das Töten, sondern die Entscheidung, wann ein Kampf überhaupt vermieden werden muss.
MD,
                'game_relevance' => [
                    'probe_hint' => 'Direkter Bezug zur Berufung Jäger: Intuition/Gewandtheit/Konstitution für Tracking, Verfolgung und Gelände.',
                    'real_world_hint' => 'Für Anfängergruppen stark, weil Jäger Ingame-Orientierung und Sicherheitsstruktur liefern können.',
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
            ->where('slug', 'heldenarchetypen-berufungen')
            ->value('id');

        if (! is_numeric($categoryId)) {
            return;
        }

        DB::table('encyclopedia_entries')
            ->where('encyclopedia_category_id', (int) $categoryId)
            ->whereIn('slug', [
                'aschemagier-der-gebrochenen-sterne',
                'schwurritter-der-grenzmark',
                'freipfad-abenteurer',
                'glutgeistliche-der-letzten-altaere',
                'wundaerzte-des-russbunds',
                'splittergelehrte-und-feldwissenschaftler',
                'schattenklingen-von-nerez',
                'krieger-der-salzpaesse',
                'barden-der-narbenchronik',
                'jaeger-der-nebelpfade',
            ])
            ->delete();

        DB::table('encyclopedia_categories')
            ->where('id', (int) $categoryId)
            ->delete();
    }
};
