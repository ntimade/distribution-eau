@extends('layouts.app')
@section('title', __('Corriger le paiement'))

@section('content')
    <div class="page-head"><h1>{{ __('Corriger le paiement') }}</h1></div>
    <div class="card narrow">
        <p class="soft">{{ __(':v1 — compteur :v2 — :v3. Montant de la facture : :v4.', ['v1' => $payment->reading->customer->name, 'v2' => $payment->reading->meter->number, 'v3' => $payment->reading->label(), 'v4' => money($payment->reading->amount)]) }}</p>
        <form method="POST" action="{{ route('payments.update', $payment) }}">
            @csrf @method('PUT')
            <div class="form-grid">
                <x-field name="amount" :label="__('Montant')" type="number" min="0" step="any" :value="(float) $payment->amount" :required="true" />
                <x-field name="paid_at" :label="__('Date')" type="date" :value="$payment->paid_at->format('Y-m-d')" :required="true" max="{{ now()->format('Y-m-d') }}" />
                <x-field name="method" :label="__('Mode')" :value="$payment->method" :placeholder="__('Facultatif')" />
                <x-field name="reference" :label="__('Référence')" :value="$payment->reference" :placeholder="__('Facultatif')" />
            </div>
            <button type="submit" class="btn">{{ __('Enregistrer') }}</button>
            <a class="btn ghost" href="{{ route('readings.show', $payment->reading_id) }}">{{ __('Annuler') }}</a>
        </form>
    </div>
@endsection
