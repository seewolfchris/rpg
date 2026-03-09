@extends('layouts.auth')

@section('title', $world->name.' | C76-RPG')

@section('meta_description', trim((string) ($world->tagline ?: $world->description ?: 'Weltansicht in C76-RPG.')))

@section('content')
    <section class="rounded-2xl border border-stone-800 bg-neutral-900/60 p-6 shadow-xl shadow-black/25">
        <p class="text-xs uppercase tracking-widest text-amber-300/80">Weltprofil</p>
        <h1 class="mt-2 font-heading text-3xl text-stone-100 sm:text-4xl">{{ $world->name }}</h1>
        @if ($world->tagline)
            <p class="mt-3 text-base text-amber-200">{{ $world->tagline }}</p>
        @endif
        <p class="mt-3 max-w-4xl text-stone-300">
            {{ $world->description ?: 'Fuer diese Welt ist noch keine Detailbeschreibung hinterlegt.' }}
        </p>

        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ route('worlds.activate', ['world' => $world]) }}" class="ui-btn ui-btn-accent inline-flex">
                Welt aktivieren
            </a>
            <a href="{{ route('campaigns.index', ['world' => $world]) }}" class="ui-btn inline-flex">
                Kampagnen in dieser Welt
            </a>
            <a href="{{ route('knowledge.index', ['world' => $world]) }}" class="ui-btn inline-flex">
                Wissenszentrum
            </a>
        </div>
    </section>

    <section class="mt-6 rounded-2xl border border-stone-800 bg-neutral-900/60 p-6">
        <h2 class="font-heading text-2xl text-stone-100">Oeffentliche Kampagnen</h2>
        @if ($featuredCampaigns->isEmpty())
            <p class="mt-3 text-sm text-stone-400">Aktuell sind keine oeffentlichen Kampagnen vorhanden.</p>
        @else
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                @foreach ($featuredCampaigns as $campaign)
                    <article class="rounded-xl border border-stone-800 bg-black/30 p-4">
                        <h3 class="font-semibold text-stone-100">{{ $campaign->title }}</h3>
                        <p class="mt-1 text-xs uppercase tracking-widest text-stone-400">
                            Leitung: {{ $campaign->owner?->name ?? 'Unbekannt' }}
                        </p>
                        <p class="mt-2 line-clamp-3 text-sm text-stone-300">{{ $campaign->summary ?: 'Keine Zusammenfassung.' }}</p>
                        @auth
                            <a
                                href="{{ route('campaigns.show', ['world' => $world, 'campaign' => $campaign]) }}"
                                class="mt-3 inline-flex text-sm font-semibold text-amber-300 hover:text-amber-200"
                            >
                                Kampagne oeffnen
                            </a>
                        @endauth
                    </article>
                @endforeach
            </div>
        @endif
    </section>
@endsection
