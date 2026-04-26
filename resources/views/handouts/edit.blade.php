@extends('layouts.auth')

@section('title', 'Handout bearbeiten | C76-RPG')

@section('content')
    <section class="mx-auto w-full max-w-4xl rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
        <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Handout-Verwaltung</p>
        <h1 class="font-heading text-3xl text-stone-100">Handout bearbeiten</h1>
        <p class="mt-2 text-stone-300">Kampagne: {{ $campaign->title }}</p>

        <form
            method="POST"
            action="{{ route('campaigns.handouts.update', ['world' => $campaign->world, 'campaign' => $campaign, 'handout' => $handout]) }}"
            class="mt-8"
            enctype="multipart/form-data"
        >
            @csrf
            @method('PATCH')
            @include('handouts._form', [
                'campaign' => $campaign,
                'handout' => $handout,
                'sceneOptions' => $sceneOptions,
                'submitLabel' => 'Handout aktualisieren',
            ])
        </form>
    </section>
@endsection
