@extends('layouts.auth')

@section('title', 'Datenschutzerklärung | Chroniken der Asche')

@section('content')
    @php($privacy = config('legal.privacy', []))
    @php($sourcePrivacyUrl = (string) data_get(config('legal.source', []), 'privacy_url', ''))

    <section class="mx-auto w-full max-w-4xl space-y-6">
        <article class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Rechtliches</p>
            <h1 class="font-heading text-3xl text-stone-100">Datenschutzerklärung</h1>
            <p class="mt-3 text-sm text-stone-300">
                Diese Erklärung ist als strukturierte Vorlage angelegt.
                Bitte alle Platzhalter und tatsächlichen betrieblichen Prozesse vor Live-Betrieb rechtlich prüfen.
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
                <p>{{ data_get($privacy, 'controller_name', 'Bitte Name eintragen') }}</p>
                @foreach (preg_split('/\r\n|\r|\n/', (string) data_get($privacy, 'controller_address', '')) as $line)
                    @if (trim($line) !== '')
                        <p>{{ $line }}</p>
                    @endif
                @endforeach
                <p>
                    E-Mail:
                    <a href="mailto:{{ data_get($privacy, 'controller_email', 'datenschutz@example.org') }}" class="text-amber-300 hover:text-amber-200">
                        {{ data_get($privacy, 'controller_email', 'datenschutz@example.org') }}
                    </a>
                </p>
            </div>
        </article>

        <article class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <h2 class="font-heading text-xl text-stone-100">2. Verarbeitete Daten und Zwecke</h2>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-stone-300">
                <li>Accountdaten (z. B. Name, E-Mail, Passwort-Hash) zur Kontoerstellung und Authentifizierung.</li>
                <li>Spielinhalte (Posts, Moderationsstatus, Revisionsstände) für den Betrieb des Play-by-Post-Systems.</li>
                <li>Nutzungs- und Sicherheitsdaten (z. B. Request-IDs, technische Logs) zur Stabilität, Missbrauchserkennung und Fehleranalyse.</li>
                <li>Benachrichtigungsdaten für In-App-, Browser- und optional E-Mail-Benachrichtigungen.</li>
            </ul>
        </article>

        <article class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <h2 class="font-heading text-xl text-stone-100">3. Empfänger und AV-Dienstleister</h2>
            @php($processors = data_get($privacy, 'processors', []))
            @if (is_array($processors) && count($processors) > 0)
                <div class="mt-3 space-y-4 text-sm text-stone-300">
                    @foreach ($processors as $processor)
                        <article class="rounded-xl border border-stone-800 bg-neutral-900/60 p-4">
                            <p class="font-semibold text-stone-100">{{ data_get($processor, 'name', 'Bitte Dienstleister eintragen') }}</p>
                            <p class="mt-1">Zweck: {{ data_get($processor, 'purpose', 'Bitte Zweck eintragen') }}</p>
                            <p class="mt-1">Standort/Angaben: {{ data_get($processor, 'location', 'Bitte Standort und AV-Status eintragen') }}</p>
                        </article>
                    @endforeach
                </div>
            @else
                <p class="mt-3 text-sm text-stone-300">Bitte AV-Dienstleister ergänzen.</p>
            @endif
        </article>

        <article class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <h2 class="font-heading text-xl text-stone-100">4. Speicherdauern</h2>
            @php($retentionPeriods = data_get($privacy, 'retention_periods', []))
            @if (is_array($retentionPeriods) && count($retentionPeriods) > 0)
                <div class="mt-3 space-y-4 text-sm text-stone-300">
                    @foreach ($retentionPeriods as $retention)
                        <article class="rounded-xl border border-stone-800 bg-neutral-900/60 p-4">
                            <p class="font-semibold text-stone-100">{{ data_get($retention, 'topic', 'Bitte Thema eintragen') }}</p>
                            <p class="mt-1">{{ data_get($retention, 'period', 'Bitte Speicherdauer eintragen') }}</p>
                        </article>
                    @endforeach
                </div>
            @else
                <p class="mt-3 text-sm text-stone-300">Bitte Speicherdauern ergänzen.</p>
            @endif
        </article>

        <article class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <h2 class="font-heading text-xl text-stone-100">5. Betroffenenrechte und Kontakt</h2>
            <p class="mt-3 text-sm text-stone-300">
                Für Auskunft, Berichtigung, Löschung, Einschränkung der Verarbeitung, Datenübertragbarkeit oder Widerspruch
                nutze bitte folgenden Kontakt:
                <a href="mailto:{{ data_get($privacy, 'rights_contact', 'datenschutz@example.org') }}" class="text-amber-300 hover:text-amber-200">
                    {{ data_get($privacy, 'rights_contact', 'datenschutz@example.org') }}
                </a>
            </p>
            <p class="mt-2 text-sm text-stone-400">
                {{ data_get($privacy, 'rights_contact_note', 'Bitte ergänzen, falls ein eigener Datenschutzkontakt oder Vertreter benannt ist.') }}
            </p>
        </article>
    </section>
@endsection
