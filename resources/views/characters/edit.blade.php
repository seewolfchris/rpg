@extends('layouts.auth')

@section('title', 'Charakter bearbeiten | C76-RPG')

@section('content')
    @include('characters.partials.form', [
        'mode' => 'edit',
        'action' => route('characters.update', $character),
        'method' => 'PUT',
        'submitLabel' => 'Charakter speichern',
        'character' => $character,
    ])
@endsection
