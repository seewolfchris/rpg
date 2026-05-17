@php
    $rolls = is_array($preview['rolls'] ?? null) ? $preview['rolls'] : [];
    $keptRoll = (int) ($preview['kept_roll'] ?? 0);
    $total = (int) ($preview['total'] ?? 0);
    $modifier = (int) ($preview['probe_modifier'] ?? 0);
    $probeTargetValue = $preview['probe_target_value'] ?? null;
    $outcomeLabel = (bool) ($preview['probe_is_success'] ?? false) ? 'Erfolg' : 'Misserfolg';
    $modeLabel = match ((string) ($preview['probe_roll_mode'] ?? 'normal')) {
        'advantage' => 'Vorteil',
        'disadvantage' => 'Nachteil',
        default => 'Normal',
    };
@endphp

<div class="rounded-md border border-amber-700/50 bg-amber-950/20 p-4" data-probe-preview-payload>
    <input type="hidden" name="probe_roll_token" value="{{ (string) ($preview['token'] ?? '') }}">

    <p class="text-xs uppercase tracking-[0.12em] text-amber-300">GM-Probe (Vorabwurf)</p>
    <p class="mt-2 text-sm text-stone-200">{{ (string) ($preview['probe_explanation'] ?? '') }}</p>

    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs uppercase tracking-[0.08em] text-stone-400">
        <span class="rounded border border-stone-600/80 bg-black/35 px-2 py-1">Held: {{ (string) ($preview['character_name'] ?? 'Unbekannt') }}</span>
        <span class="rounded border border-stone-600/80 bg-black/35 px-2 py-1">Modus: {{ $modeLabel }}</span>
        <span class="rounded border border-stone-600/80 bg-black/35 px-2 py-1">Modifikator: {{ $modifier >= 0 ? '+' : '' }}{{ $modifier }}</span>
        <span class="rounded border border-stone-600/80 bg-black/35 px-2 py-1">
            Probe auf: {{ $attributeLabel }}
            @if ($probeTargetValue !== null)
                ({{ (int) $probeTargetValue }} %)
            @endif
        </span>
    </div>

    <p class="mt-3 text-sm text-stone-200">
        Wurf: W100 = {{ $keptRoll }}
        @if ($rolls !== [] && count($rolls) > 1)
            <span class="text-xs text-stone-400">({{ implode(' / ', $rolls) }})</span>
        @endif
        <span class="text-xs text-stone-400"> | Gesamt: {{ $total }}</span>
    </p>
    <p class="mt-1 text-sm font-semibold text-amber-100">Ergebnis: {{ $outcomeLabel }}</p>

    <p class="mt-3 text-xs text-amber-200/90">
        Diese Probe ist gewürfelt und wird beim Veröffentlichen unverändert in den Beitrag übernommen.
    </p>
</div>
