@extends('layouts.auth')

@section('title', 'Welt erstellen | C76-RPG')

@section('content')
    <section class="rounded-2xl border border-stone-800 bg-neutral-900/60 p-6">
        <p class="text-xs uppercase tracking-widest text-amber-300/80">Admin</p>
        <h1 class="mt-2 font-heading text-3xl text-stone-100">Neue Welt</h1>
    </section>

    <section class="mt-6 rounded-2xl border border-stone-800 bg-neutral-900/60 p-6">
        <form method="POST" action="{{ route('admin.worlds.store') }}">
            @csrf
            @include('worlds.admin._form', [
                'world' => $world,
                'submitLabel' => 'Welt erstellen',
            ])
        </form>
    </section>
@endsection
