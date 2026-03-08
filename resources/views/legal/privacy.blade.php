@extends('layouts.auth')

@section('title', 'Datenschutzerklärung | Chroniken der Asche')

@section('content')
    @php($privacy = config('legal.privacy', []))
    @php($sourcePrivacyUrl = (string) data_get(config('legal.source', []), 'privacy_url', ''))
    @php($processors = data_get($privacy, 'processors', []))
    @php($retentionPeriods = data_get($privacy, 'retention_periods', []))

    <section class="mx-auto w-full max-w-4xl space-y-6">
        <article class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Rechtliches</p>
            <h1 class="font-heading text-3xl text-stone-100">Datenschutzerklärung</h1>
            <p class="mt-3 text-sm text-stone-300">
                Diese Datenschutzerklärung informiert über die Verarbeitung personenbezogener Daten bei der Nutzung von
                <strong>rpg.c76.org</strong> (Chroniken der Asche).
            </p>
            <p class="mt-2 text-sm text-stone-300">
                Die Plattform wird auf eigener Infrastruktur in Deutschland betrieben. Externe Analyse-, Werbe- oder
                CDN-Tracking-Dienste sind derzeit nicht aktiv.
            </p>
            @if ($sourcePrivacyUrl !== '')
                <p class="mt-2 text-sm text-stone-400">
                    Zentrale Fassung:
                    <a href="{{ $sourcePrivacyUrl }}" rel="noopener noreferrer" class="text-amber-300 hover:text-amber-200">
                        {{ $sourcePrivacyUrl }}
                    </a>
                </p>
            @endif
        </article>

        <article class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <h2 class="font-heading text-xl text-stone-100">1. Verantwortlicher</h2>
            <div class="mt-3 space-y-2 text-sm text-stone-300">
                <p>{{ data_get($privacy, 'controller_name', 'Christoph Sieber') }}</p>
                @foreach (preg_split('/\r\n|\r|\n/', (string) data_get($privacy, 'controller_address', "Bachstraße 3\n27570 Bremerhaven\nDeutschland")) as $line)
                    @if (trim($line) !== '')
                        <p>{{ $line }}</p>
                    @endif
                @endforeach
                <p>
                    E-Mail:
                    <a href="mailto:{{ data_get($privacy, 'controller_email', 'admin@c76.org') }}" class="text-amber-300 hover:text-amber-200">
                        {{ data_get($privacy, 'controller_email', 'admin@c76.org') }}
                    </a>
                </p>
            </div>
            <p class="mt-3 text-sm text-stone-300">
                Ein Datenschutzbeauftragter ist derzeit nicht benannt, da keine gesetzliche Benennungspflicht besteht.
            </p>
        </article>

        <article class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <h2 class="font-heading text-xl text-stone-100">2. Hosting und Server-Logfiles</h2>
            <div class="mt-3 space-y-3 text-sm text-stone-300">
                <p>
                    Beim Aufruf der Plattform verarbeitet der Webserver technisch erforderliche Verbindungsdaten, insbesondere:
                </p>
                <ul class="list-disc space-y-2 pl-5">
                    <li>IP-Adresse des anfragenden Endgeräts</li>
                    <li>Datum und Uhrzeit der Anfrage</li>
                    <li>angeforderte URL/Datei</li>
                    <li>übertragene Datenmenge und HTTP-Statuscode</li>
                    <li>Browser-/Systeminformationen sowie ggf. Referrer-URL</li>
                </ul>
                <p>
                    Zwecke: Bereitstellung der Anwendung, Stabilität, IT-Sicherheit, Missbrauchserkennung und Fehleranalyse.
                </p>
                <p>
                    Rechtsgrundlage: Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse am sicheren Betrieb);
                    bei registrierter Nutzung zusätzlich Art. 6 Abs. 1 lit. b DSGVO.
                </p>
            </div>
        </article>

        <article class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <h2 class="font-heading text-xl text-stone-100">3. Konto- und Spielbetrieb</h2>
            <div class="mt-3 space-y-3 text-sm text-stone-300">
                <p>Für die Nutzung von Chroniken der Asche verarbeiten wir je nach Funktion folgende Datenkategorien:</p>
                <ul class="list-disc space-y-2 pl-5">
                    <li><strong>Kontodaten:</strong> Benutzername, E-Mail-Adresse, Passwort-Hash, Rollen-/Berechtigungsdaten.</li>
                    <li><strong>Spielinhalte:</strong> Beiträge, Moderationsstatus, Read-Tracking, Revisionsdaten, Inventar- und Szenenbezüge.</li>
                    <li><strong>Betriebs- und Sicherheitsdaten:</strong> Request-IDs, technische Logs, Fehler- und Diagnoseinformationen.</li>
                </ul>
                <p>
                    Zwecke: Bereitstellung des Play-by-Post-Systems, Moderation, Schutz vor Missbrauch,
                    Sicherstellung der Integrität von Spielständen und Betriebsstabilität.
                </p>
                <p>
                    Rechtsgrundlagen: Art. 6 Abs. 1 lit. b DSGVO (Nutzungsverhältnis) und Art. 6 Abs. 1 lit. f DSGVO
                    (Betriebssicherheit, Missbrauchsabwehr, Nachvollziehbarkeit von Moderationsentscheidungen).
                </p>
            </div>
        </article>

        <article class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <h2 class="font-heading text-xl text-stone-100">4. Benachrichtigungen und Kommunikation</h2>
            <div class="mt-3 space-y-3 text-sm text-stone-300">
                <p>
                    Für In-App-, Browser- und optional E-Mail-Benachrichtigungen verarbeiten wir ereignisbezogene Daten
                    (z. B. Ereignistyp, Zeitstempel, Ziel-URL und Zustellstatus).
                </p>
                <p>
                    Browser-Benachrichtigungen werden nur nach erteilter Browser-Permission ausgeliefert.
                    Präferenzen können im Konto jederzeit angepasst werden.
                </p>
                <p>
                    Bei Kontakt per E-Mail werden Absender-/Empfängerdaten, Nachrichtentext und technische Metadaten
                    zur Bearbeitung des Anliegens verarbeitet.
                </p>
            </div>
        </article>

        <article class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <h2 class="font-heading text-xl text-stone-100">5. Cookies und lokale Speicher</h2>
            <div class="mt-3 space-y-3 text-sm text-stone-300">
                <p>
                    Es werden technisch erforderliche Cookies und lokale Speichermechanismen eingesetzt,
                    insbesondere für Sitzung, Authentifizierung, CSRF-Schutz und Zustandsverwaltung.
                </p>
                <ul class="list-disc space-y-2 pl-5">
                    <li><code>laravel_session</code> (Sitzungsverwaltung)</li>
                    <li><code>XSRF-TOKEN</code> (CSRF-Schutz)</li>
                    <li>weitere technisch notwendige Storage-Einträge für Anwendungsfunktionen</li>
                </ul>
                <p>
                    Rechtsgrundlagen: § 25 Abs. 2 Nr. 2 TDDDG für technisch erforderliche Endgerätezugriffe sowie
                    Art. 6 Abs. 1 lit. b und lit. f DSGVO.
                </p>
            </div>
        </article>

        <article class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <h2 class="font-heading text-xl text-stone-100">6. Empfänger und Auftragsverarbeitung</h2>
            <div class="mt-3 space-y-3 text-sm text-stone-300">
                <p>
                    Daten erhalten nur Stellen, die sie zur Erfüllung der genannten Zwecke benötigen.
                    Soweit Dienstleister eingesetzt werden, erfolgt dies im Rahmen der gesetzlichen Vorgaben.
                </p>
                @if (is_array($processors) && count($processors) > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full border-collapse text-left text-sm text-stone-300" aria-label="Empfänger und Auftragsverarbeitung">
                            <thead>
                                <tr class="border-b border-stone-700 text-stone-200">
                                    <th class="py-2 pr-4 font-semibold">Empfänger</th>
                                    <th class="py-2 pr-4 font-semibold">Zweck</th>
                                    <th class="py-2 font-semibold">Standort/Rolle</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($processors as $processor)
                                    <tr class="border-b border-stone-800 align-top">
                                        <td class="py-2 pr-4">{{ data_get($processor, 'name', '') }}</td>
                                        <td class="py-2 pr-4">{{ data_get($processor, 'purpose', '') }}</td>
                                        <td class="py-2">{{ data_get($processor, 'location', '') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <p>
                    Eine Übermittlung in Drittländer erfolgt auf den beschriebenen Basisdiensten derzeit nicht.
                </p>
            </div>
        </article>

        <article class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <h2 class="font-heading text-xl text-stone-100">7. Speicherdauern</h2>
            <div class="mt-3 space-y-3 text-sm text-stone-300">
                @if (is_array($retentionPeriods) && count($retentionPeriods) > 0)
                    <ul class="list-disc space-y-2 pl-5">
                        @foreach ($retentionPeriods as $retention)
                            <li>
                                <strong>{{ data_get($retention, 'topic', '') }}:</strong>
                                {{ data_get($retention, 'period', '') }}
                            </li>
                        @endforeach
                    </ul>
                @endif
                <p>
                    Soweit gesetzliche Aufbewahrungspflichten bestehen (z. B. handels- oder steuerrechtlich),
                    speichern wir Daten für die jeweils vorgeschriebenen Fristen.
                </p>
            </div>
        </article>

        <article class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <h2 class="font-heading text-xl text-stone-100">8. Pflicht zur Bereitstellung von Daten</h2>
            <div class="mt-3 space-y-3 text-sm text-stone-300">
                <p>
                    Für den bloßen Besuch der Website besteht keine gesetzliche Pflicht zur Bereitstellung personenbezogener Daten.
                </p>
                <p>
                    Für die Nutzung eines Nutzerkontos sind bestimmte Pflichtangaben (insbesondere Benutzername,
                    E-Mail-Adresse und Passwort) erforderlich. Ohne diese Angaben ist die Kontonutzung nicht möglich.
                </p>
            </div>
        </article>

        <article class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <h2 class="font-heading text-xl text-stone-100">9. Rechte betroffener Personen</h2>
            <div class="mt-3 space-y-3 text-sm text-stone-300">
                <p>Sie haben unter den gesetzlichen Voraussetzungen folgende Rechte:</p>
                <ul class="list-disc space-y-2 pl-5">
                    <li>Auskunft (Art. 15 DSGVO)</li>
                    <li>Berichtigung (Art. 16 DSGVO)</li>
                    <li>Löschung (Art. 17 DSGVO)</li>
                    <li>Einschränkung der Verarbeitung (Art. 18 DSGVO)</li>
                    <li>Datenübertragbarkeit (Art. 20 DSGVO)</li>
                    <li>Widerspruch (Art. 21 DSGVO)</li>
                    <li>Widerruf erteilter Einwilligungen (Art. 7 Abs. 3 DSGVO)</li>
                </ul>
                <p>
                    Anfragen richte bitte an:
                    <a href="mailto:{{ data_get($privacy, 'rights_contact', 'admin@c76.org') }}" class="text-amber-300 hover:text-amber-200">
                        {{ data_get($privacy, 'rights_contact', 'admin@c76.org') }}
                    </a>
                </p>
                <p class="text-stone-400">
                    {{ data_get($privacy, 'rights_contact_note', 'Datenschutzanfragen bitte mit Betreff und kurzer Sachverhaltsbeschreibung senden.') }}
                </p>
                <p>
                    Beschwerderecht bei einer Aufsichtsbehörde gemäß Art. 77 DSGVO, z. B. bei der
                    Landesbeauftragten für Datenschutz und Informationsfreiheit der Freien Hansestadt Bremen,
                    Georgstraße 122-124, 27570 Bremerhaven,
                    Telefon +49 421 361 2010 oder +49 471 596 2010,
                    E-Mail <a href="mailto:office@datenschutz.bremen.de" class="text-amber-300 hover:text-amber-200">office@datenschutz.bremen.de</a>.
                </p>
                <p>
                    Soweit Daten auf Art. 6 Abs. 1 lit. f DSGVO beruhen, besteht ein Widerspruchsrecht nach Art. 21 DSGVO.
                    Wir verarbeiten diese Daten dann nicht weiter, es sei denn, es bestehen zwingende schutzwürdige Gründe
                    oder die Verarbeitung dient der Geltendmachung, Ausübung oder Verteidigung von Rechtsansprüchen.
                </p>
                <p>
                    Eine automatisierte Entscheidungsfindung einschließlich Profiling im Sinne von Art. 22 DSGVO findet nicht statt.
                </p>
            </div>
        </article>

        <article class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <h2 class="font-heading text-xl text-stone-100">10. Datensicherheit</h2>
            <p class="mt-3 text-sm text-stone-300">
                Die Plattform wird über verschlüsselte Verbindungen (HTTPS/TLS) betrieben. Zugriffe und sicherheitsrelevante
                Prozesse werden technisch abgesichert und protokolliert, soweit dies für den sicheren Betrieb erforderlich ist.
            </p>
        </article>

        <article class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <h2 class="font-heading text-xl text-stone-100">11. Änderungen dieser Datenschutzerklärung</h2>
            <p class="mt-3 text-sm text-stone-300">
                Diese Datenschutzerklärung wird bei technischen oder rechtlichen Änderungen aktualisiert.
            </p>
            <p class="mt-2 text-xs uppercase tracking-[0.08em] text-stone-500">Stand: 08.03.2026</p>
        </article>
    </section>
@endsection
