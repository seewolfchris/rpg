@props([
    'href' => null,
    'type' => 'button',
    'variant' => 'default',
    'size' => 'default',
    'disabled' => false,
    'fullWidth' => false,
])

@php
    $variantClass = match ($variant) {
        'accent' => 'ui-btn-accent',
        'success' => 'ui-btn-success',
        'danger' => 'ui-btn-danger',
        default => '',
    };
    $sizeClass = match ($size) {
        'compact' => 'ui-action-compact',
        'large' => 'ui-action-large',
        default => '',
    };
    $classes = trim(implode(' ', array_filter([
        'ui-btn',
        'ui-action',
        $variantClass,
        $sizeClass,
        $fullWidth ? 'w-full' : '',
    ])));
    $resolvedHref = is_string($href) && trim($href) !== '' ? $href : null;
@endphp

@if ($resolvedHref !== null)
    <a
        @unless ($disabled) href="{{ $resolvedHref }}" @endunless
        @if ($disabled) aria-disabled="true" tabindex="-1" @endif
        {{ $attributes->class([$classes, 'pointer-events-none opacity-55' => $disabled]) }}
    >
        {{ $slot }}
    </a>
@else
    <button
        type="{{ $type }}"
        @disabled($disabled)
        {{ $attributes->class($classes) }}
    >
        {{ $slot }}
    </button>
@endif
