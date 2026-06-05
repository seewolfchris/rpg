@extends('layouts.auth')

@section('title', 'Erste Schritte ins Spiel · Wissenszentrum')

@section('content')
    @php($isWorldContext = isset($world) && $world instanceof \App\Models\World)

    <section class="ui-page-medium space-y-6">
        <x-navigation.context-bar
            :scope="$isWorldContext ? 'world' : 'platform'"
            :world="$isWorldContext ? $world : null"
            :items="$isWorldContext
                ? [
                    ['label' => 'Plattform', 'href' => route('home')],
                    ['label' => $world->name, 'href' => route('worlds.show', ['world' => $world])],
                    ['label' => 'Wissen', 'href' => route('knowledge.index', ['world' => $world])],
                    ['label' => 'Erste Schritte ins Spiel', 'current' => true],
                ]
                : [
                    ['label' => 'Plattform', 'href' => route('home')],
                    ['label' => 'Wissen', 'href' => route('knowledge.global.index')],
                    ['label' => 'Erste Schritte ins Spiel', 'current' => true],
                ]"
        />

        <header class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <p class="text-xs uppercase tracking-[0.14em] text-amber-400/80">Wissenszentrum</p>
            <h1 class="mt-2 font-heading text-3xl text-stone-100 sm:text-4xl">Erste Schritte ins Spiel</h1>
            <p class="mt-4 text-base leading-relaxed text-stone-300 sm:text-lg">
                Ein kompakter Einstieg für neue Spieler. Lies nur, was du für den ersten Abend brauchst:
                Welt wählen, Einladung verstehen, Charakter anlegen und den ersten Beitrag schreiben.
            </p>
        </header>

        @include('knowledge._nav')

        <section class="grid gap-4 md:grid-cols-2">
            <article class="rounded-xl border border-stone-800 bg-neutral-900/65 p-5">
                <h2 class="font-heading text-xl text-stone-100">Was ist was?</h2>
                <dl class="mt-4 space-y-3 text-sm leading-relaxed text-stone-300">
                    <div>
                        <dt class="font-semibold text-stone-100">Welt</dt>
                        <dd>Der gemeinsame Schauplatz mit eigenem Wissen, eigenen Kampagnen und eigenen Charakteroptionen.</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-stone-100">Kampagne</dt>
                        <dd>Die Spielrunde einer Spielleitung. Hier entstehen Szenen, Handouts und Story-Log.</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-stone-100">Szene</dt>
                        <dd>Der konkrete Ort der Handlung. In einer Szene liest du die letzten Beiträge und schreibst weiter.</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-stone-100">IC / OOC</dt>
                        <dd>IC ist Figurenhandlung im Spiel. OOC ist kurze Absprache außerhalb der Geschichte.</dd>
                    </div>
                </dl>
            </article>

            <article class="rounded-xl border border-stone-800 bg-neutral-900/65 p-5">
                <h2 class="font-heading text-xl text-stone-100">Dein erster Weg</h2>
                <ol class="mt-4 space-y-3 text-sm leading-relaxed text-stone-300">
                    <li><strong class="text-stone-100">1. Einladung prüfen.</strong> Wenn du eingeladen wurdest, öffne zuerst deine Kampagnen-Einladungen.</li>
                    <li><strong class="text-stone-100">2. Welt merken.</strong> Kampagne, Wissen und Charakter müssen zur gleichen aktiven Welt gehören.</li>
                    <li><strong class="text-stone-100">3. Charakter anlegen.</strong> Name, Herkunft, Berufung, Werte und kurze Biografie reichen für den Start.</li>
                    <li><strong class="text-stone-100">4. Szene lesen.</strong> Lies die Einleitung und die letzten Beiträge, bevor du schreibst.</li>
                    <li><strong class="text-stone-100">5. Kurz schreiben.</strong> Starte mit einem klaren IC-Beitrag und nutze OOC nur für knappe Hinweise.</li>
                </ol>
            </article>
        </section>

        <section class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 sm:p-8">
            <h2 class="font-heading text-2xl text-stone-100">Was du am Anfang nicht anfassen musst</h2>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <p class="rounded-xl border border-stone-800 bg-neutral-950/70 p-4 text-sm leading-relaxed text-stone-300">
                    Du musst keine Kampagne verwalten, keine Szenen erstellen und keine Moderation bedienen.
                    Das macht die Spielleitung.
                </p>
                <p class="rounded-xl border border-stone-800 bg-neutral-950/70 p-4 text-sm leading-relaxed text-stone-300">
                    Du musst keine Proben selbst auswerten. Bei unsicherem Ausgang beschreibt die Spielleitung,
                    welche Probe nötig ist und was passiert.
                </p>
                <p class="rounded-xl border border-stone-800 bg-neutral-950/70 p-4 text-sm leading-relaxed text-stone-300">
                    Du musst nicht alles im Regelwerk lesen. Für den ersten Einstieg reichen IC/OOC,
                    dein Charakter und die aktuelle Szene.
                </p>
                <p class="rounded-xl border border-stone-800 bg-neutral-950/70 p-4 text-sm leading-relaxed text-stone-300">
                    Vertrauliche Bilder oder Dateien gehören nicht in öffentliche Inline-Bilder, sondern in
                    kontrollierte Handouts der Kampagne.
                </p>
            </div>
        </section>

        <section class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 sm:p-8">
            <h2 class="font-heading text-2xl text-stone-100">Direkt weiter</h2>
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ route('campaign-invitations.index') }}" class="ui-btn ui-btn-accent">Einladungen ansehen</a>
                <a href="{{ $isWorldContext ? route('characters.create', ['world' => $world->slug]) : route('characters.create') }}" class="ui-btn">Charakter erstellen</a>
                <a href="{{ $isWorldContext ? route('campaigns.index', ['world' => $world]) : route('worlds.index') }}" class="ui-btn">Kampagnen finden</a>
                <a href="{{ $isWorldContext ? route('knowledge.how-to-play', ['world' => $world]) : route('knowledge.global.how-to-play') }}" class="ui-btn">Wie spielt man?</a>
            </div>
        </section>
    </section>
@endsection
