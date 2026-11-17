@extends('layouts.guest')
@section('title', __('Connexion'))
@section('heading', __('Connexion'))
@section('backdrop')
    @include('auth.water-backdrop')
@endsection

@section('content')
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <x-field name="organisation_code" :label="__('Code organisation')" :required="true" :placeholder="__('Ex. EDN-001')"
                 autocomplete="organization" autofocus style="text-transform: uppercase" />
        <x-field name="email" :label="__('Login (e-mail)')" type="email" :required="true" autocomplete="username" />
        <x-field name="password" :label="__('Mot de passe')" :required="true">
            <div class="password-wrap">
                <input id="password" name="password" type="password" required autocomplete="current-password">
                <button type="button" data-toggle-password>{{ __('Afficher') }}</button>
            </div>
        </x-field>
        <div class="row-between field">
            <label class="check"><input type="checkbox" name="remember"> {{ __('Se souvenir de moi') }}</label>
            <a class="small" href="{{ route('password.request') }}">{{ __('Mot de passe oublié ?') }}</a>
        </div>
        <button type="submit" class="btn block">{{ __('Se connecter') }}</button>
    </form>
    <p class="auth-foot">{{ __('Pas encore de compte ?') }} <a href="{{ route('register') }}">{{ __('Créer mon profil distributeur') }}</a></p>
@endsection
