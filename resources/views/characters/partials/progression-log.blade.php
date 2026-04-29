@php
    $progressionEvents = isset($progressionEvents) ? collect($progressionEvents) : collect();
@endphp

<section class="rounded-lg border border-emerald-700/60 bg-emerald-950/10 p-4">
    <h4 class="text-xs font-semibold uppercase tracking-widest text-emerald-200">Progressions-Log</h4>
    @if ($progressionEvents->isNotEmpty())
        <ul class="mt-3 space-y-2 text-sm text-emerald-100">
            @foreach ($progressionEvents as $progressionEvent)
                @php
                    $eventType = (string) $progressionEvent->event_type;
                    $eventLabel = match ($eventType) {
                        'xp_milestone' => 'XP-Meilenstein',
                        'xp_correction' => 'XP-Korrektur',
                        'ap_spend' => 'AP-Ausgabe',
                        'level_up_system' => 'Stufenaufstieg',
                        default => $eventType,
                    };
                    $attributeDeltas = is_array($progressionEvent->attribute_deltas) ? $progressionEvent->attribute_deltas : [];
                @endphp
                <li class="rounded border border-emerald-700/40 bg-black/20 px-3 py-2">
                    <p class="text-xs uppercase tracking-[0.08em] text-emerald-300">
                        <x-relative-time :at="$progressionEvent->created_at" />
                        • {{ $eventLabel }}
                        • {{ $progressionEvent->actorUser->name ?? 'System' }}
                    </p>
                    <p class="mt-1">
                        XP Δ {{ (int) $progressionEvent->xp_delta >= 0 ? '+' : '' }}{{ (int) $progressionEvent->xp_delta }}
                        • AP Δ {{ (int) $progressionEvent->ap_delta >= 0 ? '+' : '' }}{{ (int) $progressionEvent->ap_delta }}
                        • Stufe {{ (int) $progressionEvent->level_before }} → {{ (int) $progressionEvent->level_after }}
                    </p>
                    @if ($attributeDeltas !== [])
                        <p class="mt-1 text-xs text-emerald-200/90">
                            Attribute:
                            {{ collect($attributeDeltas)->map(fn ($value, $key): string => strtoupper((string) $key).' +'.(int) $value)->implode(', ') }}
                        </p>
                    @endif
                    @if ($progressionEvent->campaign || $progressionEvent->scene || $progressionEvent->reason)
                        <p class="mt-1 text-xs text-emerald-200/80">
                            @if ($progressionEvent->campaign)
                                Kampagne: {{ $progressionEvent->campaign->title }}
                            @endif
                            @if ($progressionEvent->scene)
                                • Szene: {{ $progressionEvent->scene->title }}
                            @endif
                            @if ($progressionEvent->reason)
                                • Grund: {{ $progressionEvent->reason }}
                            @endif
                        </p>
                    @endif
                </li>
            @endforeach
        </ul>
    @else
        <p class="mt-2 text-sm text-emerald-200/80">Noch keine Progressions-Einträge vorhanden.</p>
    @endif
</section>
