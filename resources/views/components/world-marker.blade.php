@props([
    'worldName' => '',
    'markerLabel' => '',
    'markerSymbol' => '',
    'markerBg' => '',
    'markerFg' => '',
    'markerBorder' => '',
])

@php
    $resolvedWorldName = trim((string) $worldName);
    if ($resolvedWorldName === '') {
        $resolvedWorldName = 'Unbekannte Welt';
    }

    $fallbackLabelSource = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $resolvedWorldName));
    $fallbackLabel = substr($fallbackLabelSource, 0, 3);
    if ($fallbackLabel === '') {
        $fallbackLabel = 'WEL';
    }

    $resolvedMarkerLabel = strtoupper(trim((string) $markerLabel));
    if ($resolvedMarkerLabel === '') {
        $resolvedMarkerLabel = $fallbackLabel;
    }

    $resolvedMarkerBg = trim((string) $markerBg) !== '' ? trim((string) $markerBg) : 'rgba(245, 158, 11, 0.16)';
    $resolvedMarkerFg = trim((string) $markerFg) !== '' ? trim((string) $markerFg) : 'rgb(254, 243, 199)';
    $resolvedMarkerBorder = trim((string) $markerBorder) !== '' ? trim((string) $markerBorder) : 'rgba(217, 119, 6, 0.62)';

    $markerStyle = '--world-marker-bg: '.$resolvedMarkerBg.'; --world-marker-fg: '.$resolvedMarkerFg.'; --world-marker-border: '.$resolvedMarkerBorder.';';
@endphp

<div
    {{ $attributes->class('world-marker') }}
    data-world-marker
    style="{{ $markerStyle }}"
    aria-label="Welt: {{ $resolvedWorldName }} ({{ $resolvedMarkerLabel }})"
>
    <span class="world-marker-token" aria-hidden="true">{{ $resolvedMarkerLabel }}</span>
    <span class="world-marker-separator" aria-hidden="true">·</span>
    <span class="world-marker-name">{{ $resolvedWorldName }}</span>
</div>
