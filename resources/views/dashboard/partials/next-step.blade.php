@php
    $hasSelectedWorld = $selectedWorld instanceof \App\Models\World;
    $campaignsUrl = $hasSelectedWorld
        ? route('campaigns.index', ['world' => $selectedWorld])
        : route('worlds.index');
    $primaryLabel = $hasSelectedWorld ? 'Kampagnen öffnen' : 'Welt wählen';
    $knowledgeUrl = $hasSelectedWorld
        ? route('knowledge.index', ['world' => $selectedWorld])
        : route('knowledge.global.index');
    $charactersUrl = $hasSelectedWorld
        ? route('characters.index', ['world' => $selectedWorld->slug])
        : route('characters.index');
    $howToPlayUrl = $hasSelectedWorld
        ? route('knowledge.how-to-play', ['world' => $selectedWorld])
        : route('knowledge.global.how-to-play');
@endphp

<section class="ui-card p-5 sm:p-6" aria-labelledby="dashboard-next-step-title">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="min-w-0">
            <p class="text-xs uppercase tracking-[0.14em] text-amber-400/80">Dashboard</p>
            <h2 id="dashboard-next-step-title" class="mt-1 font-heading text-2xl text-stone-100">Weiter ins Spiel</h2>
            <p class="mt-2 text-sm text-stone-300">Wähle deine nächste Aktion in der aktiven Welt.</p>
            <p class="mt-3 text-xs uppercase tracking-[0.08em] text-stone-400">
                Aktive Welt:
                <span class="text-amber-200">{{ $selectedWorld?->name ?? 'Keine Welt ausgewählt' }}</span>
            </p>
        </div>

        <a href="{{ $campaignsUrl }}" class="ui-btn ui-btn-accent inline-flex shrink-0">
            {{ $primaryLabel }}
        </a>
    </div>

    <div class="mt-4 flex flex-wrap gap-2">
        <a href="{{ $charactersUrl }}" class="ui-btn inline-flex">
            Charaktere
        </a>
        <a href="{{ $knowledgeUrl }}" class="ui-btn inline-flex">
            Wissen
        </a>
        <a href="{{ $howToPlayUrl }}" class="ui-btn inline-flex">
            Wie spielt man?
        </a>
    </div>
</section>
