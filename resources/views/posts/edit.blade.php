@extends('layouts.auth')

@section('title', 'Beitrag bearbeiten | C76-RPG')

@section('content')
    @php
        $wave3EditorPreviewEnabled = \App\Support\SensitiveFeatureGate::enabled('features.wave3.editor_preview', false);
        $wave3DraftAutosaveEnabled = \App\Support\SensitiveFeatureGate::enabled('features.wave3.draft_autosave', false);
        $wave3EditorEnhancementsEnabled = $wave3EditorPreviewEnabled || $wave3DraftAutosaveEnabled;
    @endphp
    <section class="mx-auto w-full max-w-4xl rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
        <x-navigation.back-link :href="$backUrl" label="Zurück" />
        <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Beitrag bearbeiten</p>
        <h1 class="font-heading text-3xl text-stone-100">Thread-Beitrag aktualisieren</h1>
        <p class="mt-2 text-stone-300">
            Szene:
            <a href="{{ route('campaigns.scenes.show', ['world' => $post->scene->campaign->world, 'campaign' => $post->scene->campaign, 'scene' => $post->scene]) }}" class="text-amber-300 hover:text-amber-200">
                {{ $post->scene->title }}
            </a>
        </p>

        <form
            method="POST"
            action="{{ route('posts.update', ['world' => $post->scene->campaign->world, 'post' => $post]) }}"
            enctype="multipart/form-data"
            class="mt-8"
            @if ($wave3EditorEnhancementsEnabled) data-post-editor @endif
            @if ($wave3EditorPreviewEnabled) data-preview-url="{{ route('posts.preview', ['world' => $post->scene->campaign->world]) }}" @endif
            @if ($wave3DraftAutosaveEnabled) data-draft-key="scene-{{ $post->scene_id }}-user-{{ auth()->id() }}-edit-{{ $post->id }}" @endif
        >
            @csrf
            @method('PATCH')
            @if (is_string($returnTo ?? null) && $returnTo !== '')
                <input type="hidden" name="return_to" value="{{ $returnTo }}">
            @endif
            @include('posts._form', [
                'post' => $post,
                'characters' => $characters,
                'canUseGmPostMode' => auth()->user()->can('moderate', $post),
                'showProbeControls' => false,
                'submitLabel' => 'Speichern',
                'showModerationControls' => auth()->user()->can('moderate', $post),
                'cancelUrl' => $backUrl,
            ])
        </form>
    </section>
@endsection
