@extends('layouts.app')
@section('title', __('Modifier la dette antérieure'))

@section('content')
    <div class="page-head"><h1>{{ __('Modifier la dette antérieure') }}</h1></div>
    <div class="card narrow">
        <p class="soft">{{ __(':v1 — compteur :v2. Déjà payé : :v3.', ['v1' => $balance->customer->name, 'v2' => $balance->meter->number, 'v3' => money($balance->paid_amount)]) }}</p>
        <form method="POST" action="{{ route('opening-balances.update', $balance) }}">
            @csrf @method('PUT')
            <div class="form-grid">
                <x-field name="opening_period" :label="__('Mois impayé')" type="month" :required="true" :value="$balance->period" max="{{ now()->format('Y-m') }}" />
                <x-field name="opening_balance" :label="__('Montant dû pour ce mois')" type="number" step="any" min="0" :required="true" :value="(float) $balance->amount" />
            </div>
            <button type="submit" class="btn">{{ __('Enregistrer') }}</button>
            <a class="btn ghost" href="{{ route('opening-balances.index') }}">{{ __('Annuler') }}</a>
        </form>
    </div>
@endsection
