@extends('layouts.auth')

@section('title', 'Welt bearbeiten | C76-RPG')

@section('content')
    <section class="rounded-2xl border border-stone-800 bg-neutral-900/60 p-6">
        <p class="text-xs uppercase tracking-widest text-amber-300/80">Admin</p>
        <h1 class="mt-2 font-heading text-3xl text-stone-100">Welt bearbeiten</h1>
        <p class="mt-2 text-sm text-stone-300">{{ $world->name }} ({{ $world->slug }})</p>
    </section>

    <section class="mt-6 rounded-2xl border border-stone-800 bg-neutral-900/60 p-6">
        <form method="POST" action="{{ route('admin.worlds.update', $world) }}">
            @csrf
            @method('PUT')
            @include('worlds.admin._form', [
                'world' => $world,
                'submitLabel' => 'Welt speichern',
            ])
        </form>
    </section>

    @include('worlds.admin._character_options', [
        'world' => $world,
        'speciesOptions' => $speciesOptions,
        'callingOptions' => $callingOptions,
        'templateOptions' => $templateOptions,
        'defaultTemplateKey' => $defaultTemplateKey,
    ])
@endsection
