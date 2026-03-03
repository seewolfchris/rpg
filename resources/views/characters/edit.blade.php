@extends('layouts.auth')

@section('title', 'Charakter bearbeiten | Chroniken der Asche')

@section('content')
    @include('characters.partials.form', [
        'mode' => 'edit',
        'action' => route('characters.update', $character),
        'method' => 'PUT',
        'submitLabel' => 'Charakter speichern',
        'character' => $character,
    ])
@endsection
