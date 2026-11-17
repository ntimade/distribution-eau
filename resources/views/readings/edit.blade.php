@extends('layouts.app')
@section('title', __('Corriger le relevé'))

@section('content')
    <div class="page-head"><h1>{{ __('Corriger le relevé') }}</h1></div>
    <div class="card narrow">
        <p class="soft">{{ __('Compteur :v1 — :v2. Index précédent : :v3 m³. Payé sur cette facture : :v4.', ['v1' => $reading->meter->number, 'v2' => $reading->customer->name, 'v3' => qty($reading->previous_index), 'v4' => money($reading->paid_amount)]) }}</p>
        <p class="soft small">{{ __('Le montant est recalculé. Si un relevé suit celui-ci, il repart du nouvel index et il est recalculé aussi.') }}</p>
        <form method="POST" action="{{ route('readings.update', $reading) }}">
            @csrf @method('PUT')
            <div class="form-grid">
                <x-field name="period" :label="__('Mois du relevé')" type="month" :value="$reading->period" :required="true" max="{{ now()->format('Y-m') }}" />
                <x-field name="read_at" :label="__('Date du relevé')" type="date" :value="$reading->read_at->format('Y-m-d')" :required="true" max="{{ now()->format('Y-m-d') }}" />
                <x-field name="current_index" :label="__('Nouvel index (m³)')" type="number" step="0.001" min="0" :value="(float) $reading->current_index" :required="true" />
                <x-field name="unit_price" :label="__('Prix du m³ de cette facture')" type="number" step="0.001" min="0.001" :value="(float) $reading->unit_price" :required="true" />
                <x-field name="due_date" :label="__('Échéance de cette facture')" type="date" :value="$reading->due_date?->format('Y-m-d')" />
            </div>
            <x-field name="notes" :label="__('Observation')" :value="$reading->notes" />
            <button type="submit" class="btn">{{ __('Enregistrer') }}</button>
            <a class="btn ghost" href="{{ route('readings.show', $reading) }}">{{ __('Annuler') }}</a>
        </form>
    </div>
@endsection
