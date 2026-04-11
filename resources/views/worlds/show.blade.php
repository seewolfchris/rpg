@extends('layouts.auth')

@section('title', $world->name.' | C76-RPG')

@section('meta_description', trim((string) ($world->tagline ?: $world->description ?: 'Weltansicht in C76-RPG.')))

@section('content')
    <section class="ui-card p-6 sm:p-8">
        <p class="text-xs uppercase tracking-[0.14em] text-amber-300/80">Weltprofil</p>
        <h1 class="mt-2 font-heading break-words text-3xl text-stone-100 sm:text-4xl">{{ $world->name }}</h1>
        @if ($world->tagline)
            <p class="mt-3 break-words text-base leading-relaxed text-amber-200 sm:text-lg">{{ $world->tagline }}</p>
        @endif
        <p class="mt-3 max-w-4xl break-words text-sm leading-relaxed text-stone-300 sm:text-base">
            {{ $world->description ?: 'Für diese Welt ist noch keine Detailbeschreibung hinterlegt.' }}
        </p>

        <div class="mt-6 flex flex-wrap gap-2 sm:gap-3">
            <form method="POST" action="{{ route('worlds.activate', ['world' => $world]) }}">
                @csrf
                <button type="submit" class="ui-btn ui-btn-accent inline-flex">
                    Welt betreten
                </button>
            </form>
            <a href="{{ route('campaigns.index', ['world' => $world]) }}" class="ui-btn inline-flex">
                Kampagnen
            </a>
            <a href="{{ route('knowledge.index', ['world' => $world]) }}" class="ui-btn inline-flex">
                Wissenszentrum
            </a>
        </div>
    </section>

    <section class="mt-6 ui-card-soft rounded-2xl border border-stone-800/85 bg-neutral-900/60 p-6 sm:p-7">
        <h2 class="font-heading text-2xl text-stone-100">Öffentliche Kampagnen</h2>
        @if ($featuredCampaigns->isEmpty())
            <p class="mt-3 text-sm text-stone-400">Aktuell sind keine öffentlichen Kampagnen vorhanden.</p>
        @else
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                @foreach ($featuredCampaigns as $campaign)
                    <article class="rounded-xl border border-stone-700/80 bg-black/30 p-4 shadow-md shadow-black/20">
                        <h3 class="font-semibold break-words text-stone-100">{{ $campaign->title }}</h3>
                        <p class="mt-1 text-xs uppercase tracking-[0.08em] text-stone-400">
                            Leitung: {{ $campaign->owner?->name ?? 'Unbekannt' }}
                        </p>
                        <p class="mt-2 break-words text-sm leading-relaxed text-stone-300">{{ $campaign->summary ?: 'Keine Zusammenfassung.' }}</p>
                        @auth
                            <a
                                href="{{ route('campaigns.show', ['world' => $world, 'campaign' => $campaign]) }}"
                                class="mt-3 inline-flex text-sm font-semibold text-amber-300 underline decoration-amber-500/50 underline-offset-4 hover:text-amber-100"
                            >
                                Kampagne öffnen
                            </a>
                        @endauth
                    </article>
                @endforeach
            </div>
        @endif
    </section>
@endsection
