@props([
    'items' => [],
    'scope' => null,
    'world' => null,
    'campaign' => null,
    'scene' => null,
    'showWorldSwitch' => true,
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
    $resolvedScope = trim((string) $scope);

    $worldName = trim((string) data_get($world, 'name', ''));
    $campaignTitle = trim((string) data_get($campaign, 'title', ''));
    $sceneTitle = trim((string) data_get($scene, 'title', ''));

    if ($resolvedScope === '') {
        $resolvedScope = $worldName !== '' ? 'world' : 'platform';
    }

    $scopeLabel = $resolvedScope === 'world' ? 'Weltbezogen' : 'Plattformweit';
@endphp

@if ($normalizedItems->isNotEmpty())
    <nav
        {{ $attributes->class('app-context-bar rounded-lg border border-stone-800/90 bg-black/30 px-3 py-2 text-xs') }}
        aria-label="Breadcrumb"
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <ol class="flex min-w-0 flex-wrap items-center gap-2 text-stone-300">
                @foreach ($normalizedItems as $index => $item)
                    @if ($index > 0)
                        <li aria-hidden="true" class="text-stone-500">/</li>
                    @endif

                    <li class="min-w-0">
                        @if ($index === $currentIndex)
                            <span class="font-semibold text-amber-100" aria-current="page">{{ $item['label'] }}</span>
                        @elseif (is_string($item['href']) && $item['href'] !== '')
                            <a
                                href="{{ $item['href'] }}"
                                class="app-context-link text-stone-300 underline decoration-stone-500/70 underline-offset-4 transition hover:text-stone-100 hover:decoration-stone-300/70"
                            >
                                {{ $item['label'] }}
                            </a>
                        @else
                            <span>{{ $item['label'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>

            <div class="app-context-meta flex min-w-0 flex-wrap items-center gap-2">
                <span class="app-context-chip app-context-chip-scope">{{ $scopeLabel }}</span>

                @if ($worldName !== '')
                    <span class="app-context-chip">
                        Aktive Welt: <span class="font-semibold text-stone-100">{{ $worldName }}</span>
                    </span>
                @endif

                @if ($campaignTitle !== '')
                    <span class="app-context-chip">
                        Kampagne: <span class="font-semibold text-stone-100">{{ $campaignTitle }}</span>
                    </span>
                @endif

                @if ($sceneTitle !== '')
                    <span class="app-context-chip">
                        Szene: <span class="font-semibold text-stone-100">{{ $sceneTitle }}</span>
                    </span>
                @endif

                @if ($worldName !== '' && $showWorldSwitch)
                    <a href="{{ route('worlds.index') }}" class="app-context-action">Welt wechseln</a>
                @endif
            </div>
        </div>
    </nav>
@endif
