@extends('layouts.app')
@section('title', __('Mon mot de passe'))

@section('content')
    <div class="page-head"><h1>{{ __('Mon mot de passe') }}</h1></div>

    <div class="card narrow">
        @if (auth()->user()->must_change_password && ! session('error'))
            <div class="flash error">{{ __('Choisissez un nouveau mot de passe pour continuer.') }}</div>
        @endif
        <p class="soft small">{{ __('Connecté en tant que :v1. Zones : :v2.', ['v1' => auth()->user()->email, 'v2' => auth()->user()->zonesLabel()]) }}</p>
        <form method="POST" action="{{ route('settings.password') }}">
            @csrf @method('PUT')
            <x-field name="current_password" :label="__('Mot de passe actuel')" type="password" :required="true" errorBag="password" autocomplete="current-password" />
            <div class="form-grid">
                <x-field name="password" :label="__('Nouveau mot de passe')" type="password" :required="true" minlength="8" errorBag="password" autocomplete="new-password" />
                <x-field name="password_confirmation" :label="__('Confirmer')" type="password" :required="true" autocomplete="new-password" />
            </div>
            <button type="submit" class="btn">{{ __('Changer le mot de passe') }}</button>
        </form>
    </div>
@endsection
