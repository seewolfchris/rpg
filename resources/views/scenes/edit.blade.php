@extends('layouts.auth')

@section('title', 'Szene bearbeiten | C76-RPG')

@section('content')
    <section class="mx-auto w-full max-w-4xl rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
        <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Szenenverwaltung</p>
        <h1 class="font-heading text-3xl text-stone-100">Szene bearbeiten</h1>
        <p class="mt-2 text-stone-300">Kampagne: {{ $campaign->title }}</p>

        <form method="POST" action="{{ route('campaigns.scenes.update', ['world' => $campaign->world, 'campaign' => $campaign, 'scene' => $scene]) }}" class="mt-8">
            @csrf
            @method('PUT')
            @include('scenes._form', ['campaign' => $campaign, 'scene' => $scene, 'submitLabel' => 'Speichern'])
        </form>
    </section>
@endsection

