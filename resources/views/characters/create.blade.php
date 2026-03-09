@extends('layouts.auth')

@section('title', 'Charakter erstellen | C76-RPG')

@section('content')
    @include('characters.partials.form', [
        'mode' => 'create',
        'action' => route('characters.store'),
        'method' => 'POST',
        'submitLabel' => 'Charakter erstellen',
    ])
@endsection
