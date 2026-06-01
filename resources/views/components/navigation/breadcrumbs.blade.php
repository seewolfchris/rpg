@props([
    'items' => [],
])

@php
    $normalizedItems = collect($items)
        ->map(function ($item): array {
            return [
                'label' => trim((string) data_get($item, 'label', '')),
                'href' => data_get($item, 'href'),
            ];
        })
        ->filter(fn (array $item): bool => $item['label'] !== '')
        ->values();

    $currentIndex = $normalizedItems->count() - 1;
@endphp

@if ($normalizedItems->isNotEmpty())
    <nav
        class="overflow-x-auto rounded-lg border border-stone-800/90 bg-black/25 px-3 py-2 text-xs"
        aria-label="Breadcrumb"
    >
        <ol class="flex min-w-max items-center gap-2 whitespace-nowrap text-stone-300">
            @foreach ($normalizedItems as $index => $item)
                @if ($index > 0)
                    <li aria-hidden="true" class="text-stone-500">/</li>
                @endif

                <li>
                    @if ($index === $currentIndex)
                        <span class="font-semibold text-amber-100" aria-current="page">{{ $item['label'] }}</span>
                    @elseif (is_string($item['href']) && $item['href'] !== '')
                        <a
                            href="{{ $item['href'] }}"
                            class="text-stone-300 underline decoration-stone-500/70 underline-offset-4 transition hover:text-stone-100 hover:decoration-stone-300/70"
                        >
                            {{ $item['label'] }}
                        </a>
                    @else
                        <span>{{ $item['label'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
