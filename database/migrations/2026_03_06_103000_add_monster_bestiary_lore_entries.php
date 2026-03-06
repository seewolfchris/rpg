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
                'name' => 'Monster & Bestiarium',
                'slug' => 'monster-bestiarium',
                'summary' => 'Kreaturen der Aschelande mit Feldnotizen und Richtwerten für LE, RS und Angriff.',
                'position' => 70,
                'is_public' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['slug'], ['name', 'summary', 'position', 'is_public', 'updated_at']);

        $categoryId = DB::table('encyclopedia_categories')
            ->where('slug', 'monster-bestiarium')
            ->value('id');

        if (! is_numeric($categoryId)) {
            return;
        }

        $entries = [
            [
                'title' => 'Aschenwulf',
                'slug' => 'aschenwulf',
                'excerpt' => 'Rudeljäger aus Ruß und Hunger, die Grenzpfade wie Eigentum markieren.',
                'position' => 10,
                'content' => <<<'MD'
Aschenwölfe sind keine gewöhnlichen Wölfe mit dunklem Fell, sondern Rudeltiere, die den Rauch der [Aschelande](/wissen/enzyklopaedie/regionen/aschelande) in Lunge und Blut tragen. Ihr Atem riecht nach kalter Glut, ihre Läufe sind lang und drahtig, und ihre Augen spiegeln Licht wie frisch geölter Stahl. Sie meiden große Heere, folgen aber zuverlässig kleinen Gruppen, die erschöpft, verwundet oder vom Weg abgekommen sind.

Ein Rudel jagt selten frontal. Es zieht Kreise, testet Disziplin, trennt Nachzügler und wartet auf den Moment, in dem Panik die Formation bricht. Besonders gefährlich wird es nahe alter [Blutpforten](/wissen/enzyklopaedie/kernausdruecke/blutpforte), weil Aschenwölfe dort auf Geräusche reagieren, die Menschen gar nicht hören. Manche Heiler behaupten, sie würden den Klang von Angst riechen.

In vielen Dörfern hängt man Aschesiegel über Stalltüren, um die Tiere fernzuhalten. Das hilft nur, wenn das Siegel frisch und der Schwur dahinter ehrlich ist. Falsche Zeichen machen alles schlimmer: Das Rudel bleibt länger und prüft jede Nacht die Schwachstellen.

### Spielrelevanz (Richtwerte)
- LE: 34
- RS: 2
- Angriff: 58 %

Wer im Dunkel ein leises Scharren hinter sich hört, ist oft längst Teil der Jagd.
MD,
                'game_relevance' => [
                    'le_hint' => 'Richtwert Bestiarium: LE 34.',
                    'rs_hint' => 'Richtwert Bestiarium: RS 2.',
                    'probe_hint' => 'Richtwert Angriff 58 %. Häufige GM-Proben: Intuition (Spuren), Mut (Rudelkontakt).',
                ],
            ],
            [
                'title' => 'Knochengreif',
                'slug' => 'knochengreif',
                'excerpt' => 'Aasflieger mit splitternden Schnäbeln, die Schlachtfelder leerfressen.',
                'position' => 20,
                'content' => <<<'MD'
Der Knochengreif kreist stundenlang lautlos über Sterbenden und stürzt erst, wenn niemand mehr sauber zielen kann. Seine Schwingen sind breit wie ein Marktwagen, die Federn an den Spitzen vernarbt und hart wie Horn. Er frisst nicht nur Fleisch, sondern bevorzugt Gelenke, Fingerknochen und Zähne, weshalb Schlachtfelder nach seinem Besuch aussehen wie sorgfältig geplünderte Gruben.

In der [Nebelmark](/wissen/enzyklopaedie/regionen/nebelmark) gelten Knochengreife als schlechtes Omen für Karawanen. Wenn drei Tiere gemeinsam kreisen, sprechen Händler von einem „stillen Urteil“ und schlagen sofort Nachtlager auf. Die [Rabenblütigen](/wissen/enzyklopaedie/voelker-spezies/rabenbluetige) nutzen dieses Verhalten manchmal, um Truppbewegungen vorauszuahnen: Wo Greife warten, wird bald Blut fließen.

Knochengreife meiden offenes Feuer, reagieren aber aggressiv auf glänzendes Metall und hektische Armbewegungen. Wer sie vertreiben will, braucht Ruhe, dichte Formation und Schutz über Kopf. Einzelne Schützen werden zuerst angegangen.

### Spielrelevanz (Richtwerte)
- LE: 28
- RS: 1
- Angriff: 62 %

Wenn der Schatten über dir plötzlich größer wird, reicht Beten meist nur noch für den Nachruf.
MD,
                'game_relevance' => [
                    'le_hint' => 'Richtwert Bestiarium: LE 28.',
                    'rs_hint' => 'Richtwert Bestiarium: RS 1.',
                    'probe_hint' => 'Richtwert Angriff 62 %. Häufige GM-Proben: Gewandtheit (Ausweichen), Klugheit (Deckung).',
                ],
            ],
            [
                'title' => 'Schlackentroll',
                'slug' => 'schlackentroll',
                'excerpt' => 'Massige Bruchwesen, die Mauern wie trockenes Brot brechen.',
                'position' => 30,
                'content' => <<<'MD'
Schlackentrolle entstehen dort, wo Stein, Erz und verbrannter Lehm über Jahre mit alchemischen Reststoffen versickern. Ihr Körper wirkt wie eine gehende Mauer aus Basaltplatten, in deren Ritzen rote Glutadern aufblitzen. Sie sind langsam, aber nicht träge: Wenn ein Troll den Angriff beginnt, hält er ihn durch, bis entweder das Ziel oder der Boden nachgibt.

Die [Zwerge der Basaltkämme](/wissen/enzyklopaedie/voelker-spezies/zwerge-der-basaltkaemme) kennen alte Methoden, um Schlackentrolle umzulenken, statt sie direkt zu bekämpfen. Rhythmische Hammerschläge und gezielte Vibrationen können die Kreaturen kurz irritieren. In offenen Ebenen fehlt dieser Vorteil, weshalb selbst erfahrene Trupps dort hohe Verluste tragen.

Viele Trolle suchen Ruinen aus der Zeit der [Sonnenkronen](/wissen/enzyklopaedie/zeitalter/zeitalter-der-sonnenkronen) auf, als wollten sie in alten Fundamenten etwas wiederfinden. Was sie dort suchen, weiß niemand. Sicher ist nur: Wer sie in engen Gassen bindet, lebt länger als jene, die den Nahkampf auf freiem Feld erzwingen.

### Spielrelevanz (Richtwerte)
- LE: 62
- RS: 5
- Angriff: 54 %

Wenn der erste Stein aus der Wand fällt, ist der Troll meistens schon im Raum.
MD,
                'game_relevance' => [
                    'le_hint' => 'Richtwert Bestiarium: LE 62.',
                    'rs_hint' => 'Richtwert Bestiarium: RS 5.',
                    'probe_hint' => 'Richtwert Angriff 54 %. Häufige GM-Proben: Körperkraft (Stand), Klugheit (Gelände nutzen).',
                ],
            ],
            [
                'title' => 'Blutmottenschwarm',
                'slug' => 'blutmottenschwarm',
                'excerpt' => 'Schwarmkreaturen, die Wärme spüren und Wunden binnen Sekunden öffnen.',
                'position' => 40,
                'content' => <<<'MD'
Blutmotten sind einzeln harmlos, als Schwarm jedoch ein Albtraum aus Flügelrauschen und klebriger Panik. Ihr Körper ist klein, doch die Mundwerkzeuge sind wie feine Haken geformt, die sich bevorzugt in alte Narben, frische Schnitte und Schleimhäute setzen. Deshalb richten sie in Lazaretten und Gefangenenlagern weit größeren Schaden an als auf offenen Feldern.

Sie treten gehäuft in der Nähe feuchter [Blutpforten](/wissen/enzyklopaedie/kernausdruecke/blutpforte) und stagnierender Moorkanäle auf, oft gemeinsam mit fauligem Geruch und unnatürlich warmem Nebel. [Moorwandler](/wissen/enzyklopaedie/voelker-spezies/moorwandler) tragen deshalb dichte Harzmasken und brennende Kräuterbündel am Gürtel, um Schwärme früh zu brechen.

Der Fehler vieler Gruppen ist der Versuch, jede Motte einzeln zu treffen. Wirksam ist nur Flächenkontrolle: Rauch, dichte Stofflagen, kontrollierte Feuerkegel und ein geordneter Rückzug. Wer im Schwarm stehen bleibt, verliert zuerst Übersicht, dann Blut, dann Kameraden.

### Spielrelevanz (Richtwerte)
- LE: 22 (Schwarm)
- RS: 0
- Angriff: 65 %

Wenn die Luft selbst zu summen beginnt, ist der Kampf oft schon entschieden.
MD,
                'game_relevance' => [
                    'le_hint' => 'Richtwert Bestiarium: LE 22 (Schwarmprofil).',
                    'rs_hint' => 'Richtwert Bestiarium: RS 0.',
                    'probe_hint' => 'Richtwert Angriff 65 %. Häufige GM-Proben: Konstitution (Blutverlust), Mut (Schwarmdruck).',
                ],
            ],
            [
                'title' => 'Nebelkriecher',
                'slug' => 'nebelkriecher',
                'excerpt' => 'Langgliedrige Jäger aus Dunst und Speichel, die Stimmen imitieren.',
                'position' => 50,
                'content' => <<<'MD'
Nebelkriecher bewegen sich auf vier bis sechs Gliedmaßen, je nachdem wie alt und verletzt sie sind. Ihre Haut ist halbtransparent, grau wie nasser Kalk, und ihre Zunge schmeckt nach Metall und Moder. Das Gefährlichste ist jedoch ihre Lautbildung: Sie können menschliche Stimmen in kurzen Fetzen nachahmen – oft genug, um jemandem aus der Reihe zu locken.

In den Handelsrinnen der [Nebelmark](/wissen/enzyklopaedie/regionen/nebelmark) gilt eine einfache Regel: Niemand folgt einem Ruf im Nebel, wenn der Rufer nicht sichtbar ist. Diese Regel entstand nach mehreren Wintern, in denen ganze Wachdienste verschwanden. [Kettenerben](/wissen/enzyklopaedie/voelker-spezies/kettenerben) nutzen seither Kettenzeichen und Klopfsequenzen statt Zurufen.

Nebelkriecher scheuen plötzliches, helles Licht, gewöhnen sich aber schnell daran. Ihre bevorzugte Taktik bleibt der erste Schock: falscher Name, kurzer Schrei, Angriff aus Bodennähe. Wer strukturiert kommuniziert und Abstände hält, senkt das Risiko deutlich.

### Spielrelevanz (Richtwerte)
- LE: 30
- RS: 2
- Angriff: 60 %

Wenn dich der Nebel mit deiner eigenen Stimme ruft, antworte niemals zuerst.
MD,
                'game_relevance' => [
                    'le_hint' => 'Richtwert Bestiarium: LE 30.',
                    'rs_hint' => 'Richtwert Bestiarium: RS 2.',
                    'probe_hint' => 'Richtwert Angriff 60 %. Häufige GM-Proben: Intuition (Täuschung), Klugheit (Signalprotokolle).',
                ],
            ],
            [
                'title' => 'Eidfresser',
                'slug' => 'eidfresser',
                'excerpt' => 'Ritualparasiten, die gebrochene Versprechen wittern und in Wahnsinn treiben.',
                'position' => 60,
                'content' => <<<'MD'
Eidfresser sind selten sichtbare Tiere und eher eine Art ritueller Parasit. In schwachen Momenten erscheinen sie als mageres Wesen mit zu vielen Gelenken und einer glatten, gesichtslosen Stirn. Manche Chroniken behaupten, ein Eidfresser sei nur dann vollständig körperlich, wenn im Umfeld ein schwerer Schwur gebrochen wurde. Je größer der Verrat, desto fester wird seine Gestalt.

Sie treten häufig nach missbrauchten [liturgischen Letztschwüren](/wissen/enzyklopaedie/magie-liturgie/liturgischer-letztschwur) auf und halten sich in leerstehenden Gerichtshäusern oder Schreinruinen auf. Der [Orden der Glutrichter](/wissen/enzyklopaedie/machtbloecke/orden-der-glutrichter) jagt sie unerbittlich, nicht aus Barmherzigkeit, sondern weil Eidfresser öffentliche Ordnung als Erstes zerfressen: Zeugen widersprechen sich, Richter vergessen Urteile, Truppen zweifeln Befehle an.

Im Kampf sind sie zäh und schwer zu binden. Größer als ihre Klauen ist jedoch ihr psychischer Druck. Sie zwingen Gegner oft in Momente lähmender Schuld oder in blinde Wut.

### Spielrelevanz (Richtwerte)
- LE: 45
- RS: 3
- Angriff: 57 %

Wo ein Eidfresser lauert, ist Wahrheit selten ein Schutz und fast immer ein Messer.
MD,
                'game_relevance' => [
                    'le_hint' => 'Richtwert Bestiarium: LE 45.',
                    'rs_hint' => 'Richtwert Bestiarium: RS 3.',
                    'probe_hint' => 'Richtwert Angriff 57 %. Häufige GM-Proben: Mut/Charisma gegen Schuld- und Panikdruck.',
                    'ae_hint' => 'Magische Gegenmaßnahmen sind möglich, aber oft instabil und kostenintensiv.',
                ],
            ],
            [
                'title' => 'Basaltwyrm',
                'slug' => 'basaltwyrm',
                'excerpt' => 'Tunnelbrecher unter den Bergen, halb Schlange, halb wandernder Steinschlag.',
                'position' => 70,
                'content' => <<<'MD'
Der Basaltwyrm lebt tief unter den Kämmen und frisst sich durch Schlacke, Erzadern und alte Mauerreste. Von außen wirkt er wie ein endloses Segment aus schwarzen Platten, jede so groß wie ein Schild. Sein Kopf ist keilförmig, das Maul voller flacher Mahlleisten statt klassischer Reißzähne. Er tötet weniger durch Bisse als durch Druck, Masse und den Einsturz, den seine Bewegung auslöst.

Die [Zwerge der Basaltkämme](/wissen/enzyklopaedie/voelker-spezies/zwerge-der-basaltkaemme) führen genaue Karten über mögliche Wyrmzüge. Trotzdem sterben jedes Jahr Trupps in Nebenschächten, weil ein alter Gang plötzlich „atmet“. Wer das tiefe Knacken von Stein hört, muss sofort entscheiden: Rückzug in offenen Raum oder vollständiger Stillstand an tragfähigen Stützen.

In seltenen Fällen zieht ein Wyrm bis an die Oberfläche, meist nach schweren Ritualkatastrophen oder nach Erschütterungen durch [Schlackentrolle](/wissen/enzyklopaedie/monster-bestiarium/schlackentroll). Dann wird aus einem lokalen Problem innerhalb weniger Stunden eine regionale Flucht.

### Spielrelevanz (Richtwerte)
- LE: 78
- RS: 6
- Angriff: 52 %

Wenn der Berg klingt wie eine Glocke aus Knochen, ist der Wyrm bereits unterwegs.
MD,
                'game_relevance' => [
                    'le_hint' => 'Richtwert Bestiarium: LE 78.',
                    'rs_hint' => 'Richtwert Bestiarium: RS 6.',
                    'probe_hint' => 'Richtwert Angriff 52 %. Häufige GM-Proben: Klugheit (Fluchtweg), Gewandtheit (Einsturz).',
                ],
            ],
            [
                'title' => 'Grubenhäscher',
                'slug' => 'grubenhaescher',
                'excerpt' => 'Bergwerksjäger mit Hakengliedern, die aus Dunkelschächten heraus zuschlagen.',
                'position' => 80,
                'content' => <<<'MD'
Grubenhäscher sind mittelgroße, blasse Kreaturen mit langen Unterarmen und sichelförmigen Klauen. Sie leben in stillgelegten Minen, verlassenen Belagerungsgräben und den unteren Ebenen ruinierter Festungen. Ihr Name stammt von ihrer Jagdweise: Sie hetzen nicht über Distanz, sondern reißen Opfer mit einem einzigen Zug aus der Gruppe in enge Schächte.

Viele Truppführer unterschätzen sie, weil Häschern roher Frontkampf fehlt. Ihr Vorteil ist Gelände. In schmalen Gängen, bei schlechtem Licht und unter Lärm von Wasser oder Ketten sind sie gefährlicher als manche Großbestie. [Kettenerben](/wissen/enzyklopaedie/voelker-spezies/kettenerben) sichern deshalb Minenrouten mit festen Abstandssignalen und rückwärtiger Leinenführung.

Häscher meiden starke Hitze und offene Feuerläufe, was sie in den [Aschelanden](/wissen/enzyklopaedie/regionen/aschelande) planbar macht, in feuchten Ruinen jedoch kaum. Wer gegen sie kämpft, braucht klare Sektoraufteilung und den Mut, nicht jedem Schrei sofort hinterherzulaufen.

### Spielrelevanz (Richtwerte)
- LE: 36
- RS: 2
- Angriff: 59 %

In alten Schächten tötet selten der erste Hieb – meist tötet der zweite Tunnel.
MD,
                'game_relevance' => [
                    'le_hint' => 'Richtwert Bestiarium: LE 36.',
                    'rs_hint' => 'Richtwert Bestiarium: RS 2.',
                    'probe_hint' => 'Richtwert Angriff 59 %. Häufige GM-Proben: Intuition (Hinterhalt), Gewandtheit (Zugriff entgehen).',
                ],
            ],
            [
                'title' => 'Dornenweberin',
                'slug' => 'dornenweberin',
                'excerpt' => 'Hexenhafte Waldbrut, die Fleisch und Wurzeln zu Fallen vernäht.',
                'position' => 90,
                'content' => <<<'MD'
Die Dornenweberin ist ein einzelgängerisches Raubwesen aus alten Hainen, oft dort, wo Ruinen in Wurzelwerk einsinken. Ihr Körper verbindet tierische und fast menschliche Züge: ein schmaler Rumpf, vier scharfe Greifarme und ein Kiefer, der wie ein geöffneter Dornbusch wirkt. Sie baut Jagdräume statt Nester. Jeder Pfad ist markiert, jeder Rückweg vermint, jeder Baum potenzieller Trigger.

In den Grenzgebieten der [Splitterhaine](/wissen/enzyklopaedie/voelker-spezies/elfen-der-splitterhaine) erzählen Jäger von „stummen Forsten“, in denen Vögel plötzlich schweigen. Das gilt als sicheres Zeichen für eine Weberin im Umfeld. Manche [Moorwandler](/wissen/enzyklopaedie/voelker-spezies/moorwandler) tauschen Harz und Salz gegen Wegmarken der Elfen, um solche Zonen großräumig zu umgehen.

Direkt bekämpft wird die Kreatur nur mit Vorbereitung: Brandlinien, Rückzugsachsen, klare Führungsrolle. Wer ohne Plan hineingeht, kämpft nicht gegen ein Wesen, sondern gegen dessen Gelände.

### Spielrelevanz (Richtwerte)
- LE: 40
- RS: 4
- Angriff: 56 %

Wo die Äste wie Finger zeigen, ist die Weberin selten weit entfernt.
MD,
                'game_relevance' => [
                    'le_hint' => 'Richtwert Bestiarium: LE 40.',
                    'rs_hint' => 'Richtwert Bestiarium: RS 4.',
                    'probe_hint' => 'Richtwert Angriff 56 %. Häufige GM-Proben: Klugheit (Fallen lesen), Mut (enges Gelände).',
                ],
            ],
            [
                'title' => 'Glockenleib',
                'slug' => 'glockenleib',
                'excerpt' => 'Untoter Koloss, dessen Körper wie eine geborstene Bronze-Glocke klingt.',
                'position' => 100,
                'content' => <<<'MD'
Ein Glockenleib ist keine natürliche Kreatur, sondern das Produkt misslungener Totenkulte aus der frühen Nachkriegszeit. Sein Torso erinnert an eine aufgebrochene Kirchenglocke, darunter ein Bündel aus Knochenstangen, Sehnenresten und rostigen Ketten. Jede Bewegung erzeugt dumpfe Schläge, die in engen Straßen Panik auslösen und bei manchen Hörern Übelkeit verursachen.

Glockenleiber ziehen bevorzugt durch Orte mit zerstörter Liturgie: verlassene Kapellen, umgestürzte Gerichtshöfe und Ruinen von Schwurhallen. Dort reagieren sie aggressiv auf gesprochene Eide, besonders wenn diese im Ton des [liturgischen Letztschwurs](/wissen/enzyklopaedie/magie-liturgie/liturgischer-letztschwur) geäußert werden. Die [Glutrichter](/wissen/enzyklopaedie/machtbloecke/orden-der-glutrichter) vernichten sie öffentlich, um Autorität zu demonstrieren.

Im Gefecht sind Glockenleiber langsam, aber schwer zu stoppen. Ihre Hauptwirkung liegt im Flächenstoß und in akustischer Zermürbung. Wer sie bekämpft, sollte Distanz, Deckung und Handzeichen nutzen, weil Zurufe im Glockenhall untergehen.

### Spielrelevanz (Richtwerte)
- LE: 55
- RS: 5
- Angriff: 50 %

Wenn der Boden im Takt zu beben beginnt, ist die Glocke längst für euch geläutet.
MD,
                'game_relevance' => [
                    'le_hint' => 'Richtwert Bestiarium: LE 55.',
                    'rs_hint' => 'Richtwert Bestiarium: RS 5.',
                    'probe_hint' => 'Richtwert Angriff 50 %. Häufige GM-Proben: Konstitution (Schall), Mut (Panikresistenz).',
                ],
            ],
            [
                'title' => 'Splitterhirsch',
                'slug' => 'splitterhirsch',
                'excerpt' => 'Unruhiger Waldgeist mit gläsernem Geweih und rasenden Sprints.',
                'position' => 110,
                'content' => <<<'MD'
Der Splitterhirsch wirkt auf den ersten Blick edel: hohes Wild, feiner Körperbau, majestätisches Geweih. Erst im Nahblick erkennt man die Gefahr. Das Geweih besteht aus scharfkantigen Kristallauswüchsen, die bei Bewegung feine Partikel verlieren. Diese Splitter schneiden Haut und Augen wie Glasstaub und machen selbst kurze Kontakte riskant.

Hirsche dieser Art streifen die Übergänge zwischen alten Kultplätzen und den Randzonen der [Nullfelder der Asche](/wissen/enzyklopaedie/magie-liturgie/nullfelder-der-asche). Sie sind scheu, aber extrem territorial, sobald sie verletzt oder in die Enge getrieben werden. Dann greifen sie mit überraschender Präzision an und nutzen ihre Geschwindigkeit besser als jede Reiterpatrouille.

Die [Menschen der Aschepfade](/wissen/enzyklopaedie/voelker-spezies/menschen-der-aschepfade) erzählen, dass ein gesehenes Hirschrudel Unglück ankündigt. Tatsächlich folgt auf Sichtungen oft eine Veränderung im Gelände: neue Risse, verschobene Wegmarken, plötzlich tote Brunnen. Ob der Hirsch Ursache oder Symptom ist, bleibt offen.

### Spielrelevanz (Richtwerte)
- LE: 32
- RS: 1
- Angriff: 63 %

Wenn Kristallstaub im Mondlicht glitzert, ist Flucht oft klüger als Jagd.
MD,
                'game_relevance' => [
                    'le_hint' => 'Richtwert Bestiarium: LE 32.',
                    'rs_hint' => 'Richtwert Bestiarium: RS 1.',
                    'probe_hint' => 'Richtwert Angriff 63 %. Häufige GM-Proben: Gewandtheit (Ansturm), Intuition (Territorialverhalten).',
                ],
            ],
            [
                'title' => 'Rußbanshee',
                'slug' => 'russbanshee',
                'excerpt' => 'Heulender Schattenrest gefallener Chöre, der Erinnerungen zerreißt.',
                'position' => 120,
                'content' => <<<'MD'
Rußbanshees sind geisterhafte Nachhallwesen aus verbrannten Liturgieräumen. Sie erscheinen als dunkelgraue Silhouette mit flatternden Schleierfetzen, nie ganz körperlich, aber deutlich genug, um Waffen aus der Hand zittern zu lassen. Ihr Schrei ist kein Laut im üblichen Sinn, sondern ein Druck auf Ohr, Zähne und Erinnerung. Betroffene vergessen in Sekunden die einfachsten Absprachen.

Banshees binden sich an Orte, an denen Chöre oder Schwurträger im Feuer starben. Besonders häufig werden sie in Ruinen des [Aschenfalls](/wissen/enzyklopaedie/zeitalter/der-aschenfall) gemeldet. Die [Glutverlorenen](/wissen/enzyklopaedie/voelker-spezies/glutverlorene) reagieren auf ihre Nähe oft früher als andere und gelten deshalb als wichtige Warnposten.

Gegenmaßnahmen sind begrenzt: geordnete Rückzugszeichen, feste Blickachsen und möglichst wenig offene Flamme. Einzelne liturgische Formeln können den Nachhall dämpfen, heilen ihn aber nicht. Wer in eine Banshee-Zone geht, sollte vorab festlegen, wer im Zweifel führt, wenn die Gruppe ihre eigene Stimme nicht mehr erkennt.

### Spielrelevanz (Richtwerte)
- LE: 27
- RS: 1
- Angriff: 61 %

Wenn der Schrei endet und niemand mehr weiß, wer den Befehl gab, hat die Banshee bereits gewonnen.
MD,
                'game_relevance' => [
                    'le_hint' => 'Richtwert Bestiarium: LE 27.',
                    'rs_hint' => 'Richtwert Bestiarium: RS 1.',
                    'probe_hint' => 'Richtwert Angriff 61 %. Häufige GM-Proben: Mut/Konstitution gegen Schrei- und Erinnerungsdruck.',
                    'ae_hint' => 'Magische Abwehr ist möglich, aber wegen Nachhallrisiko oft unzuverlässig.',
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
            ->where('slug', 'monster-bestiarium')
            ->value('id');

        if (! is_numeric($categoryId)) {
            return;
        }

        DB::table('encyclopedia_entries')
            ->where('encyclopedia_category_id', (int) $categoryId)
            ->whereIn('slug', [
                'aschenwulf',
                'knochengreif',
                'schlackentroll',
                'blutmottenschwarm',
                'nebelkriecher',
                'eidfresser',
                'basaltwyrm',
                'grubenhaescher',
                'dornenweberin',
                'glockenleib',
                'splitterhirsch',
                'russbanshee',
            ])
            ->delete();

        DB::table('encyclopedia_categories')
            ->where('id', (int) $categoryId)
            ->delete();
    }
};
