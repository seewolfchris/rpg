@extends('layouts.auth')

@section('title', 'Regelwerk · Wissenszentrum')

@section('content')
    <section class="mx-auto w-full max-w-5xl space-y-6">
        <header class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <p class="text-xs uppercase tracking-[0.14em] text-amber-400/80">Wissenszentrum</p>
            <h1 class="mt-2 font-heading text-3xl text-stone-100 sm:text-4xl">Regelwerk</h1>
            <p class="mt-4 text-base leading-relaxed text-stone-300 sm:text-lg">
                Das Regelwerk definiert, wie Beitragsfluss, Wuerfe und Moderation konsistent funktionieren.
            </p>
        </header>

        @include('knowledge._nav')

        <section class="space-y-4">
            <article class="rounded-xl border border-stone-800 bg-neutral-900/65 p-5">
                <h2 class="font-heading text-xl text-stone-100">1. Posting-Format</h2>
                <ul class="mt-4 space-y-2 text-sm leading-relaxed text-stone-300">
                    <li>1. IC wird in Ich-Perspektive geschrieben.</li>
                    <li>2. OOC wird klar vom IC getrennt (eigener Abschnitt oder eigener Post-Typ).</li>
                    <li>3. Spoiler nur fuer optionale oder sensible Inhalte nutzen: <code>[spoiler]...[/spoiler]</code>.</li>
                    <li>4. Edits sind erlaubt, inhaltliche Aenderungen bleiben in der Edit-History nachvollziehbar.</li>
                </ul>
            </article>

            <article class="rounded-xl border border-stone-800 bg-neutral-900/65 p-5">
                <h2 class="font-heading text-xl text-stone-100">2. Prozentproben (d100)</h2>
                <ul class="mt-4 space-y-2 text-sm leading-relaxed text-stone-300">
                    <li>1. Proben werden nur durch GM oder Co-GM ausgeloest.</li>
                    <li>2. Jede Probe braucht Anlass, Ziel-Held und Modifikator (+/-), bevor sie ausgefuehrt wird.</li>
                    <li>3. Standardprobe: 1W100 gegen den Zielwert der Aktion; Vorteil/Nachteil ist zusaetzlich moeglich.</li>
                    <li>4. Das Ergebnis wird als klarer Probe-Block im zugehoerigen GM-Post dokumentiert.</li>
                    <li>5. LE/AE-Auswirkungen werden direkt am Charakterbogen persistiert.</li>
                </ul>
            </article>

            <article class="rounded-xl border border-stone-800 bg-neutral-900/65 p-5">
                <h2 class="font-heading text-xl text-stone-100">3. Moderation und Freigabe</h2>
                <ul class="mt-4 space-y-2 text-sm leading-relaxed text-stone-300">
                    <li>1. Neue Posts koennen als <code>pending</code> in die Moderation gehen.</li>
                    <li>2. GM/Admin setzen Status auf <code>approved</code> oder <code>rejected</code>.</li>
                    <li>3. Moderationswechsel werden im Audit-Log dokumentiert.</li>
                    <li>4. Freigegebene Posts zaehlen fuer Punkte/Gamification.</li>
                </ul>
            </article>

            <article class="rounded-xl border border-stone-800 bg-neutral-900/65 p-5">
                <h2 class="font-heading text-xl text-stone-100">4. Fair-Play und Safety</h2>
                <ul class="mt-4 space-y-2 text-sm leading-relaxed text-stone-300">
                    <li>1. Kein Godmodding: Du kontrollierst nur deinen eigenen Charakter.</li>
                    <li>2. Content-Warnungen bei Gewalt, Folter, sexualisierter Gewalt und Triggern.</li>
                    <li>3. Respekt vor Pausen und asynchronen Antwortzeiten.</li>
                    <li>4. Streit OOC klaeren, nicht IC eskalieren.</li>
                </ul>
            </article>
        </section>
    </section>
@endsection
