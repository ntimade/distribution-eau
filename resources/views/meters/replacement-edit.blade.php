@extends('layouts.app')
@section('title', __('Corriger le remplacement'))

@section('content')
    <div class="page-head"><h1>{{ __('Corriger le remplacement') }}</h1></div>
    <div class="card narrow">
        <p class="soft">{{ __('Compteur :v1 remplacé par :v2. Index précédent de l\'ancien compteur : :v3 m³.', ['v1' => $replacement->oldMeter->number, 'v2' => $replacement->newMeter->number, 'v3' => qty($replacement->old_previous_index)]) }}</p>
        @if ($replacement->reading)
            <p class="soft small">{{ __('La facture :v1 qui regroupe les deux compteurs sera recalculée.', ['v1' => of_period($replacement->reading->period)]) }}</p>
        @endif
        <form method="POST" action="{{ route('meters.replacements.update', $replacement) }}">
            @csrf @method('PUT')
            <div class="form-grid">
                <x-field name="old_final_index" :label="__('Index final de l\'ancien compteur (m³)')" type="number" step="0.001" min="0" :required="true" :value="(float) $replacement->old_final_index" />
                <x-field name="replaced_on" :label="__('Date du remplacement')" type="date" :required="true" :value="$replacement->replaced_on->format('Y-m-d')" max="{{ now()->format('Y-m-d') }}" />
            </div>
            <x-field name="notes" :label="__('Observation')" :value="$replacement->notes" />
            <button type="submit" class="btn">{{ __('Enregistrer') }}</button>
            <a class="btn ghost" href="{{ route('meters.show', $replacement->new_meter_id) }}">{{ __('Annuler') }}</a>
        </form>
    </div>
@endsection
