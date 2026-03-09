@extends('layouts.auth')

@section('title', 'Bookmarks | Chroniken der Asche')

@section('content')
    <section class="mx-auto w-full max-w-6xl space-y-6">
        <div class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Persönliche Navigation</p>
                    <h1 class="font-heading text-3xl text-stone-100">Szenen-Bookmarks</h1>
                    <p class="mt-3 text-sm text-stone-300">Gespeicherte Marker: <span class="font-semibold text-amber-200">{{ $totalCount }}</span></p>
                </div>

                <a
                    href="{{ route('scene-subscriptions.index') }}"
                    class="rounded-md border border-stone-600/80 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                >
                    Zu Abos
                </a>
            </div>
        </div>

        <section class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <form method="GET" action="{{ route('bookmarks.index') }}" class="grid gap-3 md:grid-cols-[1fr_auto]">
                <input
                    type="text"
                    name="q"
                    value="{{ $search }}"
                    placeholder="Suche nach Label, Szene oder Kampagne ..."
                    class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-4 py-2.5 text-sm text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                >

                <button
                    type="submit"
                    class="rounded-md border border-amber-500/60 bg-amber-500/15 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-500/30"
                >
                    Filtern
                </button>
            </form>
        </section>

        <section class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            @if ($bookmarks->isEmpty())
                <p class="text-sm text-stone-400">Keine Bookmarks für den gewählten Filter.</p>
            @else
                <div class="space-y-3">
                    @foreach ($bookmarks as $bookmark)
                        @php($bookmarkScene = $bookmark->scene)
                        @if ($bookmarkScene && $bookmarkScene->campaign)
                            <article class="rounded-xl border border-stone-800 bg-neutral-900/60 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm text-stone-100">
                                            <span class="font-semibold">{{ $bookmarkScene->title }}</span>
                                            <span class="text-stone-500">• {{ $bookmarkScene->campaign->title }}</span>
                                        </p>
                                        <p class="mt-2 text-xs uppercase tracking-[0.08em] text-stone-500">
                                            @if ($bookmark->label)
                                                Label: {{ $bookmark->label }} •
                                            @endif
                                            Gesetzt: {{ $bookmark->updated_at?->format('d.m.Y H:i') }}
                                            @if ($bookmark->post_id)
                                                • Post #{{ $bookmark->post_id }}
                                            @endif
                                        </p>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2">
                                        <a
                                            href="{{ $bookmarkJumpUrls[$bookmark->id] ?? route('campaigns.scenes.show', [$bookmarkScene->campaign, $bookmarkScene]) }}"
                                            class="rounded-md border border-emerald-600/70 bg-emerald-900/20 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-emerald-200 transition hover:bg-emerald-900/35"
                                        >
                                            Öffnen
                                        </a>

                                        <form method="POST" action="{{ route('campaigns.scenes.bookmark.destroy', [$bookmarkScene->campaign, $bookmarkScene]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="rounded-md border border-red-700/80 bg-red-900/20 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-red-200 transition hover:bg-red-900/40"
                                            >
                                                Entfernen
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </article>
                        @endif
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $bookmarks->links() }}
                </div>
            @endif
        </section>
    </section>
@endsection
