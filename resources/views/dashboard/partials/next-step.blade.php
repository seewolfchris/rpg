@php
    $nextStep = $dashboardNextStep ?? null;
@endphp

<section class="ui-card p-5 sm:p-6" aria-labelledby="dashboard-next-step-title">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="min-w-0">
            <p class="text-xs uppercase tracking-[0.14em] text-amber-400/80">{{ $nextStep?->eyebrow ?? 'Nächste Aktion' }}</p>
            <h2 id="dashboard-next-step-title" class="mt-1 font-heading text-2xl text-stone-100">{{ $nextStep?->title ?? 'Weiter ins Spiel' }}</h2>
            <p class="mt-2 text-sm text-stone-300">{{ $nextStep?->description ?? 'Wähle deine nächste Aktion in der aktiven Welt.' }}</p>
            @if ($nextStep?->meta)
                <p class="mt-3 text-xs uppercase tracking-[0.08em] text-stone-400">{{ $nextStep->meta }}</p>
            @endif
        </div>

        <x-ui.action
            :href="$nextStep?->primaryUrl ?? route('worlds.index')"
            variant="accent"
            class="shrink-0"
        >
            {{ $nextStep?->primaryLabel ?? 'Welt wählen' }}
        </x-ui.action>
    </div>

    @if ($nextStep?->secondaryLabel && $nextStep?->secondaryUrl)
        <div class="mt-4 flex flex-wrap gap-2">
            <x-ui.action :href="$nextStep->secondaryUrl">
                {{ $nextStep->secondaryLabel }}
            </x-ui.action>
        </div>
    @endif
</section>
