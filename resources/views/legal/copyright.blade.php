@extends('layouts.auth')

@section('title', 'Urheberrecht | Chroniken der Asche')

@section('content')
    @php($copyright = config('legal.copyright', []))

    <section class="mx-auto w-full max-w-4xl space-y-6">
        <article class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Rechtliches</p>
            <h1 class="font-heading text-3xl text-stone-100">Urheberrecht und Rechtehinweise</h1>
            <p class="mt-3 text-sm text-stone-300">
                Alle Inhalte auf dieser Plattform sind urheberrechtlich geschützt, soweit nicht anders gekennzeichnet.
                Die Nutzung ist nur im Rahmen der geltenden Gesetze und der freigegebenen Plattformfunktionen zulässig.
            </p>
        </article>

        <article class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <h2 class="font-heading text-xl text-stone-100">Meldung möglicher Rechtsverletzungen</h2>
            <p class="mt-3 text-sm text-stone-300">
                Für Hinweise zu möglichen Urheberrechts- oder Markenrechtsverletzungen nutze bitte:
                <a href="mailto:{{ data_get($copyright, 'rights_contact', 'rechte@example.org') }}" class="text-amber-300 hover:text-amber-200">
                    {{ data_get($copyright, 'rights_contact', 'rechte@example.org') }}
                </a>
            </p>
            <p class="mt-2 text-sm text-stone-400">
                {{ data_get($copyright, 'rights_contact_note', 'Bitte ergänzen, wie Rechteanfragen bevorzugt eingereicht werden sollen (E-Mail, Betreff, Nachweise).') }}
            </p>
        </article>

        <article class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <h2 class="font-heading text-xl text-stone-100">Hinweis zu Drittinhalten</h2>
            <p class="mt-3 text-sm text-stone-300">
                Inhalte Dritter werden nach Hinweis und Prüfung entfernt oder korrigiert,
                sofern eine rechtliche Verletzung vorliegt.
            </p>
        </article>
    </section>
@endsection
