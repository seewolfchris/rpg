@extends('layouts.auth')

@section('title', 'Kampagnen | C76-RPG')

@section('content')
    @php($returnTo = request()->getRequestUri())
    <section class="ui-page-wide space-y-6">
        <x-navigation.context-bar
            scope="world"
            :world="$world"
            :items="[
                ['label' => 'Plattform', 'href' => route('home')],
                ['label' => $world->name, 'href' => route('worlds.show', ['world' => $world])],
                ['label' => 'Kampagnen', 'current' => true],
            ]"
        />

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Chroniken und Handlungsbögen · {{ $world->name }}</p>
                <h1 class="font-heading text-3xl text-stone-100">Kampagnen</h1>
                <p class="mt-2 text-stone-300">Wähle eine laufende Kampagne oder starte einen neuen Storybogen.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a
                    href="{{ route('worlds.index') }}"
                    class="rounded-md border border-stone-600/80 px-5 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                >
                    Welt wechseln
                </a>
                @can('create', App\Models\Campaign::class)
                    <a
                        href="{{ route('campaigns.create', ['world' => $world, 'return_to' => $returnTo]) }}"
                        class="rounded-md border border-amber-400/70 bg-amber-500/20 px-5 py-3 text-sm font-semibold uppercase tracking-[0.12em] text-amber-100 transition hover:bg-amber-400/30"
                    >
                        Neue Kampagne
                    </a>
                @endcan
            </div>
        </div>

        @if ($campaigns->isEmpty())
            <x-empty-state
                title="Keine Kampagnen in dieser Welt"
                description="In dieser Welt gibt es aktuell keine Kampagne, der du beigetreten bist. Du kannst eine neue Kampagne starten oder eine andere Welt wählen."
                :hint="auth()->user()->can('create', App\Models\Campaign::class) ? '' : 'Du hast aktuell keine Berechtigung, selbst Kampagnen zu erstellen.'"
            >
                <x-slot name="actions">
                    @can('create', App\Models\Campaign::class)
                        <a href="{{ route('campaigns.create', ['world' => $world, 'return_to' => $returnTo]) }}" class="ui-btn ui-btn-accent">Neue Kampagne</a>
                    @endcan
                    <a href="{{ route('worlds.index') }}" class="ui-btn">Welt wechseln</a>
                </x-slot>
            </x-empty-state>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($campaigns as $campaign)
                    <article class="rounded-xl border border-stone-800 bg-neutral-900/65 p-5 shadow-lg shadow-black/30">
                        <div class="flex items-start justify-between gap-3">
                            <h2 class="font-heading text-xl text-stone-100">{{ $campaign->title }}</h2>
                            <span class="rounded border border-stone-600/80 bg-black/40 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-stone-300">
                                {{ $campaign->status }}
                            </span>
                        </div>

                        @if ($campaign->summary)
                            <p class="mt-3 line-clamp-3 text-sm leading-relaxed text-stone-300">{{ $campaign->summary }}</p>
                        @endif

                        <p class="mt-3 text-xs uppercase tracking-[0.09em] text-stone-500">
                            Leitung: {{ $campaign->owner->name }}
                        </p>

                        <div class="mt-4 flex items-center gap-2">
                            @if ($campaign->is_public)
                                <span class="rounded border border-emerald-600/60 bg-emerald-900/20 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-emerald-300">Public</span>
                            @else
                                <span class="rounded border border-amber-600/60 bg-amber-900/20 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-amber-300">Intern</span>
                            @endif
                            @if (! $campaign->is_public && $campaign->owner_id !== auth()->id() && ($campaign->is_invited ?? false))
                                <span class="rounded border border-sky-700/70 bg-sky-900/20 px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] text-sky-300">Eingeladen</span>
                            @endif
                        </div>

                        <a
                            href="{{ route('campaigns.show', ['world' => $world, 'campaign' => $campaign, 'return_to' => $returnTo]) }}"
                            class="mt-5 inline-flex rounded-md border border-stone-600/80 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                        >
                            Öffnen
                        </a>
                    </article>
                @endforeach
            </div>

            <div>
                {{ $campaigns->links() }}
            </div>
        @endif
    </section>
@endsection
