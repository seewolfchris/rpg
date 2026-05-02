@extends('layouts.auth')

@section('title', $campaign->title.' | Notiz erstellen')

@section('content')
    <section class="mx-auto w-full max-w-4xl space-y-6">
        <div class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <x-navigation.back-link :href="$backUrl" label="Zurück" />

            <div class="mt-3">
                <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Meine Notizen</p>
                <h1 class="font-heading break-words text-2xl text-stone-100 sm:text-3xl">Notiz erstellen</h1>
                <p class="mt-2 text-sm text-stone-300">Private Gedanken, Hinweise oder offene Fragen zu dieser Kampagne.</p>
            </div>

            <form method="POST" action="{{ route('campaigns.player-notes.store', ['world' => $campaign->world, 'campaign' => $campaign]) }}" class="mt-6">
                @csrf
                @if (is_string($returnTo ?? null) && $returnTo !== '')
                    <input type="hidden" name="return_to" value="{{ $returnTo }}">
                @endif
                @include('player-notes._form', [
                    'playerNote' => $playerNote,
                    'sceneOptions' => $sceneOptions,
                    'characterOptions' => $characterOptions,
                    'submitLabel' => 'Notiz erstellen',
                    'cancelUrl' => $backUrl,
                ])
            </form>
        </div>
    </section>
@endsection
