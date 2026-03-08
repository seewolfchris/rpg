@extends('layouts.auth')

@section('title', 'Impressum | Chroniken der Asche')

@section('content')
    @php($imprint = config('legal.imprint', []))
    @php($sourceImprintUrl = (string) data_get(config('legal.source', []), 'imprint_url', ''))
    @php($phone = trim((string) data_get($imprint, 'contact_phone', '')))
    @php($phone = $phone !== '' ? $phone : 'auf Anfrage per E-Mail')

    <section class="mx-auto w-full max-w-4xl space-y-6">
        <article class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Rechtliches</p>
            <h1 class="font-heading text-3xl text-stone-100">Impressum</h1>
            <p class="mt-3 text-sm text-stone-300">
                Diese Seite enthält Pflichtangaben für den Anbieter nach deutschem Recht.
            </p>
            <p class="mt-2 text-sm text-stone-400">
                {{ data_get($imprint, 'scope_note', 'Dieses Impressum gilt für c76.org und zugehörige Subdomains, inklusive rpg.c76.org.') }}
            </p>
            @if ($sourceImprintUrl !== '')
                <p class="mt-2 text-sm text-stone-400">
                    Zentrale Fassung:
                    <a href="{{ $sourceImprintUrl }}" rel="noopener noreferrer" class="text-amber-300 hover:text-amber-200">
                        {{ $sourceImprintUrl }}
                    </a>
                </p>
            @endif
        </article>

        <article class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <h2 class="font-heading text-xl text-stone-100">Angaben zum Diensteanbieter</h2>
            <div class="mt-3 space-y-2 text-sm text-stone-300">
                <p>{{ data_get($imprint, 'responsible_name', 'Bitte Name eintragen') }}</p>
                @foreach (preg_split('/\r\n|\r|\n/', (string) data_get($imprint, 'responsible_address', '')) as $line)
                    @if (trim($line) !== '')
                        <p>{{ $line }}</p>
                    @endif
                @endforeach
            </div>
        </article>

        <article class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <h2 class="font-heading text-xl text-stone-100">Kontakt</h2>
            <dl class="mt-3 space-y-2 text-sm text-stone-300">
                <div>
                    <dt class="font-semibold text-stone-200">E-Mail</dt>
                    <dd>
                        <a href="mailto:{{ data_get($imprint, 'contact_email', 'kontakt@example.org') }}" class="text-amber-300 hover:text-amber-200">
                            {{ data_get($imprint, 'contact_email', 'kontakt@example.org') }}
                        </a>
                    </dd>
                </div>
                <div>
                    <dt class="font-semibold text-stone-200">Telefon</dt>
                    <dd>{{ $phone }}</dd>
                </div>
            </dl>
        </article>

        <article class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <h2 class="font-heading text-xl text-stone-100">Inhaltlich verantwortlich</h2>
            <p class="mt-3 text-sm text-stone-300">
                {{ data_get($imprint, 'content_responsible', 'Bitte verantwortliche Person für redaktionelle Inhalte eintragen') }}
            </p>
        </article>
    </section>
@endsection
