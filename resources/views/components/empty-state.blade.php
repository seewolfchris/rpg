@props([
    'title',
    'description' => '',
    'hint' => '',
])

@php
    $descriptionText = trim((string) $description);
    $hintText = trim((string) $hint);
    $actionsHtml = isset($actions) ? trim((string) $actions) : '';
    $slotHtml = trim((string) $slot);
@endphp

<div {{ $attributes->class('ui-empty-state') }}>
    <div class="ui-empty-state-marker" aria-hidden="true"></div>
    <div class="min-w-0">
        <h2 class="font-heading text-xl text-stone-100">{{ $title }}</h2>

        @if ($descriptionText !== '')
            <p class="mt-3 text-sm leading-relaxed text-stone-300">{{ $descriptionText }}</p>
        @endif

        @if ($hintText !== '')
            <p class="mt-3 text-xs leading-relaxed text-stone-400">{{ $hintText }}</p>
        @endif

        @if ($actionsHtml !== '')
            <div class="mt-5 flex flex-wrap gap-2">
                {{ $actions }}
            </div>
        @endif

        @if ($slotHtml !== '')
            <div class="mt-4 text-sm leading-relaxed text-stone-300">
                {{ $slot }}
            </div>
        @endif
    </div>
</div>
