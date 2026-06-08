@extends('layouts.auth')

@section('title', 'Benutzer erstellen | C76-RPG')

@section('content')
    <section class="ui-page-wide rounded-2xl border border-stone-800 bg-neutral-900/60 p-6">
        <p class="text-xs uppercase tracking-widest text-amber-300/80">Admin</p>
        <h1 class="mt-2 font-heading text-3xl text-stone-100 sm:text-4xl">Benutzer erstellen</h1>
        <p class="mt-3 max-w-3xl text-sm text-stone-300">
            Admin-erstellte Benutzer erhalten keine automatisch gespeicherte Terms-Zustimmung.
        </p>
    </section>

    <section class="ui-page-wide mt-6 rounded-2xl border border-stone-800 bg-neutral-900/60 p-4 sm:p-6">
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            @include('admin.users._form', [
                'user' => null,
                'submitLabel' => 'Benutzer erstellen',
                'cancelUrl' => route('admin.users.index'),
            ])
        </form>
    </section>
@endsection
