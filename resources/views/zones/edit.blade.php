@extends('layouts.app')
@section('title', __('Modifier la zone'))

@section('content')
    <div class="page-head"><h1>{{ __('Modifier la zone') }}</h1></div>
    <div class="card narrow">
        <form method="POST" action="{{ route('zones.update', $zone) }}">
            @csrf @method('PUT')
            <x-field name="name" :label="__('Nom')" :value="$zone->name" :required="true" />
            <x-field name="description" :label="__('Description')" :value="$zone->description" />
            <button type="submit" class="btn">{{ __('Enregistrer') }}</button>
            <a class="btn ghost" href="{{ route('zones.index') }}">{{ __('Annuler') }}</a>
        </form>
    </div>
@endsection
