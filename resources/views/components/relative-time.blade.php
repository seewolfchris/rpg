@props([
    'at' => null,
    'format' => 'd.m.Y H:i',
])

@if ($at instanceof \Carbon\CarbonInterface)
    <time datetime="{{ $at->toIso8601String() }}" title="{{ $at->format($format) }}">
        {{ $at->locale(app()->getLocale())->diffForHumans() }}
    </time>
@else
    <span>-</span>
@endif
