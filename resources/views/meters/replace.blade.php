@extends('layouts.app')
@section('title', __('Remplacer le compteur'))

@section('content')
    <div class="page-head"><h1>{{ __('Remplacer le compteur :v1', ['v1' => $meter->number]) }}</h1></div>
    <div class="card narrow">
        <p class="soft">{{ __('Client : :v1. Dernier index connu : :v2 m³.', ['v1' => $meter->customer?->name ?? 'aucun', 'v2' => qty($lastIndex)]) }}</p>
        <p class="soft small">{{ __('L\'ancien compteur sera désactivé. Ce qu\'il a consommé depuis son dernier relevé sera ajouté à la prochaine facture du nouveau compteur : une seule facture, et une seule fois les frais d\'entretien du mois. Les factures et dettes déjà enregistrées restent sur l\'ancien compteur.') }}</p>
        <form method="POST" action="{{ route('meters.replace.store', $meter) }}">
            @csrf
            <h2>{{ __('Ancien compteur') }}</h2>
            <div class="form-grid">
                <x-field name="old_final_index" :label="__('Index final (m³)')" type="number" step="0.001" min="0" :required="true" autofocus
                         :help="__('Si le compteur est illisible, saisissez une estimation et indiquez-le dans l\'observation.')" />
                <x-field name="replaced_on" :label="__('Date du remplacement')" type="date" :value="now()->format('Y-m-d')" :required="true" max="{{ now()->format('Y-m-d') }}" />
            </div>

            <h2>{{ __('Nouveau compteur') }}</h2>
            <div class="form-grid">
                <x-field name="number" :label="__('Numéro du nouveau compteur')" :required="true" />
                <x-field name="initial_index" :label="__('Index à la pose (m³)')" type="number" step="0.001" min="0" :required="true" :value="0" />
            </div>

            <x-field name="notes" :label="__('Observation')" />
            <button type="submit" class="btn">{{ __('Remplacer le compteur') }}</button>
            <a class="btn ghost" href="{{ route('meters.show', $meter) }}">{{ __('Annuler') }}</a>
        </form>
    </div>
@endsection
