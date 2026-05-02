@extends('layouts.auth')

@section('title', $playerNote->title.' | Notiz bearbeiten')

@section('content')
    <section class="mx-auto w-full max-w-4xl space-y-6">
        <div class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <x-navigation.back-link :href="$backUrl" label="Zurück" />

            <div class="mt-3">
                <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Meine Notizen</p>
                <h1 class="font-heading break-words text-2xl text-stone-100 sm:text-3xl">Notiz bearbeiten</h1>
            </div>

            <form method="POST" action="{{ route('campaigns.player-notes.update', ['world' => $campaign->world, 'campaign' => $campaign, 'playerNote' => $playerNote]) }}" class="mt-6">
                @csrf
                @method('PATCH')
                @if (is_string($returnTo ?? null) && $returnTo !== '')
                    <input type="hidden" name="return_to" value="{{ $returnTo }}">
                @endif
                @include('player-notes._form', [
                    'playerNote' => $playerNote,
                    'sceneOptions' => $sceneOptions,
                    'characterOptions' => $characterOptions,
                    'submitLabel' => 'Speichern',
                    'cancelUrl' => $backUrl,
                ])
            </form>
        </div>
    </section>
@endsection
