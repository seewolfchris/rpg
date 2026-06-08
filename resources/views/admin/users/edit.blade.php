@extends('layouts.auth')

@section('title', 'Benutzer bearbeiten | C76-RPG')

@section('content')
    <section class="ui-page-wide rounded-2xl border border-stone-800 bg-neutral-900/60 p-6">
        <p class="text-xs uppercase tracking-widest text-amber-300/80">Admin</p>
        <h1 class="mt-2 font-heading text-3xl text-stone-100 sm:text-4xl">Benutzer bearbeiten</h1>
        <p class="mt-3 text-sm text-stone-300">
            {{ $user->name }} · {{ $user->email }}
        </p>
    </section>

    <section class="ui-page-wide mt-6 rounded-2xl border border-stone-800 bg-neutral-900/60 p-4 sm:p-6">
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf
            @method('PATCH')
            @include('admin.users._form', [
                'user' => $user,
                'submitLabel' => 'Benutzer speichern',
                'cancelUrl' => route('admin.users.show', $user),
            ])
        </form>
    </section>
@endsection
