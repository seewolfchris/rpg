@extends('layouts.auth')

@section('title', 'Chronik-Eintrag erstellen | C76-RPG')

@section('content')
    <section class="mx-auto w-full max-w-4xl rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
        <x-navigation.back-link :href="$backUrl" label="Zurück" />
        <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Neue Chronik</p>
        <h1 class="font-heading text-3xl text-stone-100">Eintrag erstellen</h1>
        <p class="mt-2 text-stone-300">Kampagne: {{ $campaign->title }}</p>

        <form method="POST" action="{{ route('campaigns.story-log.store', ['world' => $campaign->world, 'campaign' => $campaign]) }}" class="mt-8">
            @csrf
            @if (is_string($returnTo ?? null) && $returnTo !== '')
                <input type="hidden" name="return_to" value="{{ $returnTo }}">
            @endif
            @include('story-log._form', [
                'campaign' => $campaign,
                'storyLogEntry' => $storyLogEntry,
                'sceneOptions' => $sceneOptions,
                'submitLabel' => 'Eintrag speichern',
                'cancelUrl' => $backUrl,
            ])
        </form>
    </section>
@endsection
