@extends('layouts.auth')

@section('title', 'Hilfe · Chroniken der Asche')

@section('content')
    <section class="space-y-8">
        <header class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 sm:p-8">
            <p class="text-xs uppercase tracking-[0.14em] text-amber-400/80">Einsteiger-Guide</p>
            <h1 class="mt-2 font-heading text-3xl text-stone-100 sm:text-4xl">Hilfe & Begriffe</h1>
            <p class="mt-4 max-w-3xl text-base leading-relaxed text-stone-300 sm:text-lg">
                Diese Seite erklaert die wichtigsten Begriffe fuer das Play-by-Post-RPG und gibt dir klare Regeln,
                wie du sauber in Szenen postest.
            </p>
        </header>

        <section class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 sm:p-8">
            <h2 class="font-heading text-2xl text-stone-100">Glossar (kurz erklaert)</h2>

            <dl class="mt-5 grid gap-4 md:grid-cols-2">
                <div class="rounded-lg border border-stone-800 bg-neutral-900/70 p-4">
                    <dt class="font-heading text-sm tracking-[0.08em] text-amber-300">PbP (Play-by-Post)</dt>
                    <dd class="mt-2 text-sm text-stone-300">Asynchrones Rollenspiel per Text-Posts statt Live-Session.</dd>
                </div>
                <div class="rounded-lg border border-stone-800 bg-neutral-900/70 p-4">
                    <dt class="font-heading text-sm tracking-[0.08em] text-amber-300">IC (In Character)</dt>
                    <dd class="mt-2 text-sm text-stone-300">Alles, was deine Figur in der Welt sagt, denkt oder tut.</dd>
                </div>
                <div class="rounded-lg border border-stone-800 bg-neutral-900/70 p-4">
                    <dt class="font-heading text-sm tracking-[0.08em] text-amber-300">OOC (Out Of Character)</dt>
                    <dd class="mt-2 text-sm text-stone-300">Spieler-Kommentare ausserhalb der Figur (Absprachen, Fragen).</dd>
                </div>
                <div class="rounded-lg border border-stone-800 bg-neutral-900/70 p-4">
                    <dt class="font-heading text-sm tracking-[0.08em] text-amber-300">GM / Co-GM</dt>
                    <dd class="mt-2 text-sm text-stone-300">Spielleitung einer Kampagne; Co-GM mit erweiterten Rechten.</dd>
                </div>
                <div class="rounded-lg border border-stone-800 bg-neutral-900/70 p-4">
                    <dt class="font-heading text-sm tracking-[0.08em] text-amber-300">Kampagne / Szene</dt>
                    <dd class="mt-2 text-sm text-stone-300">Kampagne = Oberrahmen. Szene = einzelner Story-Thread innerhalb der Kampagne.</dd>
                </div>
                <div class="rounded-lg border border-stone-800 bg-neutral-900/70 p-4">
                    <dt class="font-heading text-sm tracking-[0.08em] text-amber-300">Dice Roll / d20</dt>
                    <dd class="mt-2 text-sm text-stone-300">Wuerfelwurf im System. Ergebnis wird im Log gespeichert.</dd>
                </div>
                <div class="rounded-lg border border-stone-800 bg-neutral-900/70 p-4">
                    <dt class="font-heading text-sm tracking-[0.08em] text-amber-300">Spoiler</dt>
                    <dd class="mt-2 text-sm text-stone-300">Versteckter Text fuer optionale Infos oder sensible Inhalte.</dd>
                </div>
                <div class="rounded-lg border border-stone-800 bg-neutral-900/70 p-4">
                    <dt class="font-heading text-sm tracking-[0.08em] text-amber-300">Moderation</dt>
                    <dd class="mt-2 text-sm text-stone-300">GM/Admin pruefen Inhalte und koennen Posts freigeben oder ablehnen.</dd>
                </div>
            </dl>
        </section>

        <section class="grid gap-6 lg:grid-cols-2">
            <article class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 sm:p-8">
                <h2 class="font-heading text-2xl text-stone-100">IC vs OOC Beispiel</h2>
                <div class="mt-5 space-y-4 text-sm leading-relaxed text-stone-300">
                    <div class="rounded-lg border border-stone-700 bg-neutral-900/70 p-4">
                        <p class="text-xs uppercase tracking-[0.12em] text-amber-300">IC</p>
                        <p class="mt-2">"Arvid tritt durch den Nebel und hebt die Klinge. "Wenn du luegst, endet es hier.""</p>
                    </div>
                    <div class="rounded-lg border border-stone-700 bg-neutral-900/70 p-4">
                        <p class="text-xs uppercase tracking-[0.12em] text-amber-300">OOC</p>
                        <p class="mt-2">"Ich antworte morgen nochmal auf den Zauberwurf. Heute nur kurzer Post."</p>
                    </div>
                </div>
            </article>

            <article class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 sm:p-8">
                <h2 class="font-heading text-2xl text-stone-100">Posting-Regeln (empfohlen)</h2>
                <ul class="mt-5 space-y-3 text-sm leading-relaxed text-stone-300">
                    <li>1. Trenne IC und OOC klar in jedem Beitrag.</li>
                    <li>2. Respektiere bestehende Szenen-Reihenfolge und Story-Kontinuitaet.</li>
                    <li>3. Nutze Spoiler fuer Meta-Infos oder sensible Inhalte.</li>
                    <li>4. Nutze den Dice-Roller statt manueller Wuerfelangaben.</li>
                    <li>5. Editiere Posts transparent, wenn sich der Sinn aendert.</li>
                    <li>6. Bei Konflikten zuerst OOC kurz klaeren, dann IC weiterspielen.</li>
                </ul>
            </article>
        </section>

        <section class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 sm:p-8">
            <h2 class="font-heading text-2xl text-stone-100">Schnellstart fuer neue Spieler</h2>
            <ol class="mt-5 space-y-3 text-sm leading-relaxed text-stone-300">
                <li>1. Registrieren und einloggen.</li>
                <li>2. Charakter erstellen (Stats, Bio, Bild).</li>
                <li>3. Einer Kampagne beitreten oder Einladung annehmen.</li>
                <li>4. Szene oeffnen und IC-Post verfassen.</li>
                <li>5. Bei Proben den eingebauten d20-Wuerfel nutzen.</li>
            </ol>
        </section>
    </section>
@endsection
