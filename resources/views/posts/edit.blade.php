@extends('layouts.auth')

@section('title', 'Beitrag bearbeiten | Chroniken der Asche')

@section('content')
    <section class="mx-auto w-full max-w-4xl rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
        <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Beitrag bearbeiten</p>
        <h1 class="font-heading text-3xl text-stone-100">Thread-Beitrag aktualisieren</h1>
        <p class="mt-2 text-stone-300">
            Szene:
            <a href="{{ route('campaigns.scenes.show', [$post->scene->campaign, $post->scene]) }}" class="text-amber-300 hover:text-amber-200">
                {{ $post->scene->title }}
            </a>
        </p>

        <form method="POST" action="{{ route('posts.update', $post) }}" class="mt-8">
            @csrf
            @method('PATCH')
            @include('posts._form', [
                'post' => $post,
                'characters' => $characters,
                'showProbeControls' => false,
                'submitLabel' => 'Speichern',
                'showModerationControls' => auth()->user()->can('moderate', $post),
            ])
        </form>
    </section>
@endsection
