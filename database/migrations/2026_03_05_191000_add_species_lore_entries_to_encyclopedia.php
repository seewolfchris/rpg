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
                'name' => 'Voelker & Spezies',
                'slug' => 'voelker-spezies',
                'summary' => 'Urspruenge, Schwuere und Narben der Voelker von Vhal\'Tor.',
                'position' => 50,
                'is_public' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['slug'], ['name', 'summary', 'position', 'is_public', 'updated_at']);

        $categoryId = DB::table('encyclopedia_categories')
            ->where('slug', 'voelker-spezies')
            ->value('id');

        if (! is_numeric($categoryId)) {
            return;
        }

        $entries = [
            [
                'title' => 'Menschen der Aschepfade',
                'slug' => 'menschen-der-aschepfade',
                'excerpt' => 'Ueberlebende zwischen Glut, Schulden und letzten Schwueren.',
                'position' => 10,
                'content' => <<<'MD'
Menschen sind in Vhal'Tor kein ruhiges Zentrum, sondern ein wanderndes Fieber aus Schuld, Hoffnung und Hunger. Seit dem [Aschenfall](/wissen/enzyklopaedie/zeitalter/der-aschenfall) ziehen ganze Sippen ueber die alten Heerstrassen, binden Wunden mit Asche und nennen jede Nacht ohne Ueberfall einen Segen. Sie haben keine uralte Magie in den Knochen und keinen steinernen Schwur, der sie bindet; gerade deshalb passen sie sich an jede Verzerrung schneller an als viele alte Voelker.

Ihre Staedte wachsen oft um Ruinen, Pilgerfeuer oder notduerftige Zollhaeuser. Heute verkauft ein Mensch Brot, morgen fuehrt er einen Karawanenzug durch die Nebelmark, uebermorgen traegt er den letzten Eid eines gefallenen Hauses. In den [Aschelanden](/wissen/enzyklopaedie/regionen/aschelande) nennt man sie halb spottend, halb ehrfuerchtig "Pfadvolk", weil sie selbst aus verbrannter Erde noch Wege schneiden. Wo andere in Blutlinien denken, rechnen Menschen in Tagen, Schulden und Gelegenheiten.

Unter ihnen entstehen die haertesten Bande und die schnellsten Verrate. Ein gegebener Schwur kann Leben retten, aber oft reicht eine verlorene Ernte, damit Brueder einander in Schattenhaeuser verkaufen. Vielleicht ist genau das ihre groesste Gabe: Menschen tragen keine angeborene Rolle, sie schmieden sie aus Narben.

Und wenn der Wind den Rauch ueber die Strassen treibt, sind es meist menschliche Schritte, die zuerst im Dunkel verschwinden.
MD,
                'game_relevance' => [
                    'le_hint' => 'Menschen starten ohne pauschale LE-Boni und balancieren ueber Werte, Ausruestung und Entscheidungen.',
                    'ae_hint' => 'Real-World Anfaenger starten als Mensch ohne Astralenergie; Magie kann nur spaeter ingame entdeckt werden.',
                    'real_world_hint' => 'Im Charakterbogen ist bei Herkunft "Real-World Anfaenger" aktuell ausschliesslich Mensch erlaubt.',
                ],
            ],
            [
                'title' => 'Elfen der Splitterhaine',
                'slug' => 'elfen-der-splitterhaine',
                'excerpt' => 'Feinsinnige Grenzwaechter zwischen verbotenen Liedern und kalten Sternen.',
                'position' => 20,
                'content' => <<<'MD'
Die Elfen der Splitterhaine leben dort, wo das Licht wie zerschnittenes Glas auf schwarze Blaetter faellt. Ihre Haine entstanden an alten Bruchlinien der Welt, nahe vergessener [Blutpforten](/wissen/enzyklopaedie/kernausdruecke/blutpforte), und jedes Kind lernt frueh, dass ein schoener Klang auch ein Ruf in die Verzerrung sein kann. Elfen sprechen leise, aber nie leichtfertig; Worte tragen fuer sie Gewicht wie Klingen fuer Menschen.

Viele reisen als Kundschafter, Heiler oder Sternleser durch fremde Lager und kaufen sich mit Wissen statt mit Silber durch feindliche Tore. Sie lesen Muster in Rauch und Vogelzuegen, erkennen Luegen an Atem und Pausen. Doch ihre Naehe zu den Stroemungen kostet sie rohe Koerperkraft und eine einfache Zugehoerigkeit zu menschlichen Machtspielen. Den [Orden der Glutrichter](/wissen/enzyklopaedie/machtbloecke/orden-der-glutrichter) macht genau das misstrauisch: Zu viel Ahnung von Magie, zu wenig Ehrfurcht vor Dogma.

In den Splitterhainen gilt ein alter Lehrsatz: "Wer den Schatten versteht, darf ihn nicht lieben." Deshalb tragen viele Elfen kleine Aschesiegel an Kette oder Handgelenk, nicht als Zwang, sondern als Erinnerung an Grenzen. Wenn ein Elf sein Siegel freiwillig zerbricht, ist das selten ein Akt der Freiheit; meist beginnt dann ein stiller Krieg gegen die eigene Stimme.

Und am Ende bleibt oft nur ein Lied, das niemand bei Tageslicht singen will.
MD,
                'game_relevance' => [
                    'ae_hint' => 'Elfen koennen im Charakterbogen astrale Ressourcen erhalten, sofern Spezies und Berufung dies stuetzen.',
                    'probe_hint' => 'GM-Proben auf Intuition/Charisma sind bei elfischen Figuren oft erzahlerisch naheliegend.',
                    'real_world_hint' => 'Real-World Anfaenger koennen nicht direkt als Elf starten; elfische Linien bleiben vorerst lore-seitig.',
                ],
            ],
            [
                'title' => 'Zwerge der Basaltkaemme',
                'slug' => 'zwerge-der-basaltkaemme',
                'excerpt' => 'Steinharte Eidtraeger aus Feuerhallen ueber den schwarzen Schluchten.',
                'position' => 30,
                'content' => <<<'MD'
Zwischen den zerbrochenen Bergruecken der Basaltkaemme liegen Hallen, deren Tore nur bei Vulkanfrost geoeffnet werden. Dort schmieden Zwerge nicht bloss Waffen, sondern Erinnerungen: Jede Klinge traegt Kerben fuer Schwuere, jede Ruestung einen stillen Nachruf. Seit dem Ende der Sonnenkronen halten sie die tiefen Passwege offen, solange der Zoll in Erz, Blut oder Treue gezahlt wird.

Zwerge gelten als zaeh, weil sie in engen Schaechten aufwachsen, wo Rauch die Lunge frisst und Fehltritte in bodenlose Glut fallen. Sie lernen frueh, dass Standhaftigkeit mehr bedeutet als Stolz: Wer wankt, reisst die ganze Kette in die Tiefe. Darum wirken sie in offenen Hoefen oft hart, fast unbeweglich, waehrend sie unter Tage eine peinlich genaue Sprache fuer Vertrauen, Scham und Schuld pflegen. Mit den [Schattenhaeusern von Nerez](/wissen/enzyklopaedie/machtbloecke/schattenhaeuser-von-nerez) handeln sie nur ueber Mittler, weil verdeckte Namen fuer sie ein Zeichen von Feigheit sind.

Viele Basaltzwerge tragen ein [Aschesiegel](/wissen/enzyklopaedie/kernausdruecke/aschesiegel) am Unterarm, das Herkunft und Schwurlinie ausweist. Es schuetzt nicht vor Verrat, macht ihn aber fuer alle sichtbar. Wer ein Siegel faelscht, wird nicht vor Gericht gestellt, sondern aus allen Hallen gestrichen, als haette er nie gelebt.

Und in einer Welt aus Staub ist Vergessen haerter als jeder Hammer.
MD,
                'game_relevance' => [
                    'le_hint' => 'Zwergische Konzepte profitieren oft von hoher Konstitution und soliden LE-Polstern.',
                    'rs_hint' => 'Die Kultur der Basaltkaemme harmoniert mit schweren Ruestungen und verlaesslicher Parade.',
                    'probe_hint' => 'GM-Proben auf Koerperkraft/Konstitution unter Druck passen haeufig zu zwergischen Szenen.',
                ],
            ],
            [
                'title' => 'Schwurgeborene',
                'slug' => 'schwurgeborene',
                'excerpt' => 'Kinder gebrochener Eide, gezeichnet von den letzten Schwueren der Alten.',
                'position' => 40,
                'content' => <<<'MD'
Schwurgeborene sind kein eigenes Reich und keine saubere Blutlinie. Sie entstehen dort, wo alte Eide unter Gewalt, Hunger oder Magie gebrochen wurden und die Kinder die Last als Narbe weitertragen. In manchen Regionen gelten sie als unheilvoll, in anderen als lebende Mahnung, dass jeder Letzte Schwur einen Preis fordert. Viele tragen vom Jugendalter an feine Rissmuster in Haut oder Stimme, als wuerde etwas in ihnen staendig nachhallen.

Sie suchen oft Orte mit starker Symbolik auf: zerbrochene Schreinplaetze, Grenzsteine, ausgebrannte Ratshaeuser. Dort hoeren sie angeblich Echos derjenigen, die den Schwur einst sprachen. Das macht sie fuer Priester, Magistrate und Kriegsherren gleichermassen attraktiv und gefaehrlich. Die [Glutrichter](/wissen/enzyklopaedie/machtbloecke/orden-der-glutrichter) dulden sie, solange sie gehorchen; die [Schattenhaeuser](/wissen/enzyklopaedie/machtbloecke/schattenhaeuser-von-nerez) kaufen ihre Loyalitaet mit Schutzversprechen, die selten halten.

Im Alltag sind Schwurgeborene haeufig Vermittler in unmoeglichen Verhandlungen, weil sie beide Seiten an den Bruch erinnern koennen. Doch dieser Vorteil frisst ihre Ruhe: Wer zu viele fremde Schwuere traegt, verliert irgendwann den eigenen Namen hinter Pflicht und Scham.

Wenn ein Schwurgeborener schweigt, ist das selten Frieden, sondern meist der Moment vor einem neuen Bruch.
MD,
                'game_relevance' => [
                    'probe_hint' => 'Starke Grundlage fuer soziale GM-Proben mit Risiko, etwa auf Charisma, Mut oder Intuition.',
                    'real_world_hint' => 'Schwurgeborene sind vorerst lore-only und nicht als direkte Spezieswahl im Charakterbogen angelegt.',
                ],
            ],
            [
                'title' => 'Rabenbluetige',
                'slug' => 'rabenbluetige',
                'excerpt' => 'Schattenkundige Linienhaeuser mit Blick fuer Schuld und bevorstehendes Sterben.',
                'position' => 50,
                'content' => <<<'MD'
Rabenbluetige stammen aus zerstreuten Linien, die sich selbst auf die Nacht des ersten grossen Massengrabs zurueckfuehren. Ob diese Herkunft wahr ist, weiss niemand sicher; wahr ist nur, dass Raben ihre Lager ungewoehnlich oft umkreisen, als haetten sie einen stillen Vertrag mit den Toten. In staedtischen Chroniken werden sie mal als Seuchenboten, mal als unerwuenschte Propheten verzeichnet.

Sie erkennen Gefahren frueh, nicht durch Zaubersprueche, sondern durch kleinste Zeichen: zu lange Stille in einem Hof, der falsche Geruch im Regen, zu frische Kreide an einem Schuldbuch. Viele handeln mit Informationen, Leichenrechten oder dem Aufspueren verlorener Erben. In der [Nebelmark](/wissen/enzyklopaedie/regionen/nebelmark) fuehren sie Listen ueber Verschollene, die laenger ueberleben als jedes Amt. Das bringt sie regelmaessig in Konflikt mit den [Schattenhaeusern](/wissen/enzyklopaedie/machtbloecke/schattenhaeuser-von-nerez), die lieber vergessen als dokumentieren.

Rabenbluetige meiden laute Eide. Sie bevorzugen kurze, harte Abmachungen, die auf Knochen, Metall oder Messer gezeichnet werden. Wer sie taeuscht, wird selten sofort bestraft; zuerst wird alles notiert, dann entzogen, dann geloescht. Ihr Zorn ist keine Flamme, sondern ein langer Winter.

Und wenn der erste Rabe auf deinem Dach landet, ist die Rechnung oft schon geschrieben.
MD,
                'game_relevance' => [
                    'probe_hint' => 'Geeignet fuer Ermittlungs- und Wahrnehmungsszenen mit GM-Proben auf Intuition/Klugheit.',
                    'real_world_hint' => 'Rabenbluetige sind aktuell reine Lore-Spezies; Charakterbogen bleibt bei Mensch/Elf/Zwerg.',
                ],
            ],
            [
                'title' => 'Moorwandler',
                'slug' => 'moorwandler',
                'excerpt' => 'Grenzgaenger aus Nebel, Torf und stillen Opferpfaden.',
                'position' => 60,
                'content' => <<<'MD'
Moorwandler leben an den fauligen Raendern von Wasser, Nebel und halb versunkenen Strassen. Sie bauen keine grossen Mauern, sondern bewegliche Siedlungen aus Holzstegen, Seilen und sinkenden Plattformen, die nur Eingeweihte sicher betreten. Fremde sehen in ihnen oft Schmuggler oder Hexenknechte; die Wahrheit ist einfacher und haerter: Moorwandler ueberleben dort, wo feste Ordnung untergeht.

Ihre Kinder lernen zuerst Gehoer, dann Sprache. Ein falscher Tritt, ein zu lautes Feuer oder ein unbedachter Schwur kann im Moor ganze Familien kosten. Deshalb fuehren Moorwandler ihre Konflikte mit Geduld, Fallen und Rueckzug statt mit offenen Liniengefechten. Sie kennen die Preise der [Blutpforten](/wissen/enzyklopaedie/kernausdruecke/blutpforte), weil manche Pforten im Moor nicht schliessen, sondern atmen wie Wunden. Wer sie bewachen will, braucht nicht Mut allein, sondern Disziplin gegen den eigenen Schrecken.

Mit den [Aschelanden](/wissen/enzyklopaedie/regionen/aschelande) sind sie ueber Karawanenpfade verbunden: Torf, Heilpflanzen, sumpfgehaertetes Leder gegen Salz, Stahl und Nachrichten. Doch jedes Handelsbuenis bleibt fragil, weil Moorwandler keinem Vertrag trauen, der laenger lebt als der Mann, der ihn sprach.

Und wenn der Nebel Namen verschluckt, erinnern sich nur noch ihre Messer an den Weg.
MD,
                'game_relevance' => [
                    'probe_hint' => 'Starker Aufhaenger fuer Ueberlebens- und Spurenproben unter widrigen Umweltbedingungen.',
                    'rs_hint' => 'Moorwaffen und leichte Ruestungskonzepte passen gut zu Beweglichkeit statt schwerem Schutz.',
                    'real_world_hint' => 'Moorwandler sind derzeit lore-only und nicht direkt als Spezies auswaehlbar.',
                ],
            ],
            [
                'title' => 'Kettenerben',
                'slug' => 'kettenerben',
                'excerpt' => 'Nachfahren gebundener Legionen, diszipliniert und von Pflicht gezeichnet.',
                'position' => 70,
                'content' => <<<'MD'
Kettenerben sind die spaeten Nachkommen jener Straflegionen, die nach dem Fall der Sonnenkronen nicht entlassen, sondern an neue Herren verkauft wurden. Aus den Eisenketten ihrer Ahnen wurde ein ganzer Lebenskodex: Ordnung vor Trost, Pflicht vor Stolz, Zusammenhalt vor Herkunft. Viele tragen noch heute Kettenglieder am Guertel, nicht als Schmuck, sondern als Chronik ihrer Linie.

In Grenzfestungen und auf bedrohten Handelswegen gelten Kettenerben als verlaesslich, solange der Sold stimmt und der Befehl klar bleibt. Was sie verachten, sind doppelte Befehle und verborgene Klauseln. Darum hassen sie die Schuldvertraege der [Schattenhaeuser von Nerez](/wissen/enzyklopaedie/machtbloecke/schattenhaeuser-von-nerez), waehrend sie mit den [Glutrichtern](/wissen/enzyklopaedie/machtbloecke/orden-der-glutrichter) eine kalte Zweckallianz teilen: Beide glauben, dass Chaos nur durch harte Grenzen zaehlbar bleibt.

Kettenerben bauen Familien wie Formationen. Kinder werden frueh in Waffenpflege, Rationskunde und Wachdienst eingefuehrt, aber ebenso in das Brechen von Befehlsketten, wenn ein Eid offen missbraucht wird. Das macht sie zu gefaehrlichen Gegnern tyrannischer Fuehrer: Sie kennen Gehorsam und wissen, wann er zur Schande wird.

Und wenn eine Kette reisst, faellt nicht nur Metall, sondern meist ein ganzes Banner in die Asche.
MD,
                'game_relevance' => [
                    'le_hint' => 'Passt zu frontnahen Archetypen mit Fokus auf LE, Disziplin und kontrolliertes Risiko.',
                    'probe_hint' => 'GM-Proben auf Mut, Koerperkraft oder Gewandtheit lassen sich hier gut verankern.',
                    'real_world_hint' => 'Kettenerben sind aktuell nicht als direkte Spezieswahl implementiert.',
                ],
            ],
            [
                'title' => 'Glutverlorene',
                'slug' => 'glutverlorene',
                'excerpt' => 'Ueberlebende verbrannter Rituale, halb Mensch und halb Erinnerung.',
                'position' => 80,
                'content' => <<<'MD'
Glutverlorene sind jene Ungluecklichen, die ein zerbrochenes Ritual, eine entgleiste Liturgie oder den Riss einer [Blutpforte](/wissen/enzyklopaedie/kernausdruecke/blutpforte) ueberlebt haben, ohne je wieder ganz zurueckzukehren. Ihre Haut traegt feine Brandadern, ihre Augen reagieren auf Feuer mit Furcht und Sehnsucht zugleich, und in stillen Stunden sprechen manche mit Stimmen, die nicht zu ihnen gehoeren. Viele Gemeinden vertreiben sie aus Angst vor Ansteckung durch Unglueck.

Trotzdem werden Glutverlorene gesucht. Alchemisten, Kriegspriester und verbotene Zirkel glauben, in ihnen die Karte zur naechsten grossen Macht zu sehen. Die [Glutrichter](/wissen/enzyklopaedie/machtbloecke/orden-der-glutrichter) brandmarken manche als Gefahr, schuetzen andere als "Zeugen wider die Ketzerei" und verlieren dabei oft selbst den Massstab. Glutverlorene lernen schnell, dass Mitleid selten kostenlos ist.

Untereinander pflegen sie ein stilles Gesetz: Niemand fragt nach dem genauen Preis, den der andere gezahlt hat. Stattdessen tauschen sie Routen, Heilmittel, Namen korrupter Heiler und Orte, an denen Feuer nicht sofort Hass bedeutet. Ihre Gemeinschaft ist bruechig, aber ehrlich; wer dort luegt, wird nicht verflucht, sondern allein gelassen.

Und in einer Welt, die alles Verlorene ausbluten laesst, ist Einsamkeit oft der letzte Rest von Gnade.
MD,
                'game_relevance' => [
                    'ae_hint' => 'Kein automatischer Magieanspruch: AE haengt weiterhin von Spezies/Berufung und Kampagnenentscheid ab.',
                    'probe_hint' => 'Hoher narrativer Hebel fuer Kontroll-, Angst- oder Widerstandsproben durch den GM.',
                    'real_world_hint' => 'Glutverlorene bleiben vorerst Lore-only; Real-World Figuren starten weiterhin ohne diesen Status.',
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
            ->where('slug', 'voelker-spezies')
            ->value('id');

        if (! is_numeric($categoryId)) {
            return;
        }

        DB::table('encyclopedia_entries')
            ->where('encyclopedia_category_id', (int) $categoryId)
            ->whereIn('slug', [
                'menschen-der-aschepfade',
                'elfen-der-splitterhaine',
                'zwerge-der-basaltkaemme',
                'schwurgeborene',
                'rabenbluetige',
                'moorwandler',
                'kettenerben',
                'glutverlorene',
            ])
            ->delete();

        DB::table('encyclopedia_categories')
            ->where('id', (int) $categoryId)
            ->delete();
    }
};
