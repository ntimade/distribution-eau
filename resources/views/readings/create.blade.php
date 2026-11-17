@extends('layouts.app')
@section('title', __('Saisir un relevé'))

@php
    $generalPrice = auth()->user()->organisation->price_per_m3;
@endphp

@section('content')
    <div class="page-head"><h1>{{ __('Saisir un relevé') }}</h1></div>
    <div class="card narrow">
        @if ($generalPrice === null && ! $meters->contains(fn ($meter) => $meter->customer->price_per_m3 !== null))
            <p class="empty">{{ __('Renseignez d\'abord le prix du mètre cube.') }} <a href="{{ route('settings.edit') }}">{{ __('Aller aux paramètres') }}</a></p>
        @elseif ($meters->isEmpty())
            <p class="empty">{{ __('Aucun compteur actif rattaché à un client.') }} <a href="{{ route('meters.create') }}">{{ __('Ajouter un compteur') }}</a></p>
        @else
            <form method="POST" action="{{ route('readings.store') }}">
                @csrf
                <x-field name="meter_id" :label="__('Compteur')" :required="true">
                    <select id="meter_id" name="meter_id" required>
                        <option value="">{{ __('Choisir…') }}</option>
                        @foreach ($meters as $meter)
                            @php($price = $meter->customer->price_per_m3 ?? $generalPrice)
                            <option value="{{ $meter->id }}" data-previous="{{ (float) ($meter->last_index ?? $meter->initial_index) }}" data-price="{{ (float) $price }}"
                                    @disabled($price === null) @selected(old('meter_id', $selectedMeter) == $meter->id)>{{ $meter->number }} — {{ $meter->customer->name }}@if ($price === null) {{ __('(sans prix)') }}@endif</option>
                        @endforeach
                    </select>
                </x-field>
                <div class="form-grid">
                    <x-field name="period" :label="__('Mois du relevé')" type="month" :value="$period" :required="true" max="{{ now()->format('Y-m') }}" />
                    <x-field name="read_at" :label="__('Date du relevé')" type="date" :value="now()->format('Y-m-d')" :required="true" max="{{ now()->format('Y-m-d') }}" />
                </div>
                <x-field name="current_index" :label="__('Nouvel index (m³)')" type="number" step="0.001" min="0" :required="true" />
                <div class="preview" id="preview" hidden></div>
                <x-field name="notes" :label="__('Observation')" />
                <button type="submit" class="btn">{{ __('Enregistrer le relevé') }}</button>
                <a class="btn ghost" href="{{ route('readings.index', ['period' => $period]) }}">{{ __('Annuler') }}</a>
            </form>
        @endif
    </div>
@endsection

@if ($meters->isNotEmpty() && ($generalPrice !== null || $meters->contains(fn ($meter) => $meter->customer->price_per_m3 !== null)))
@push('scripts')
    <script>
        (function () {
            var decimals = {{ auth()->user()->organisation->currencyDecimals() }};
            var currency = @json(auth()->user()->organisation->currency);
            var fees = @json($maintenanceFees);
            var period = document.getElementById('period');
            var meter = document.getElementById('meter_id');
            var index = document.getElementById('current_index');
            var preview = document.getElementById('preview');
            var format = function (value, digits) {
                return value.toLocaleString(document.documentElement.lang, { minimumFractionDigits: digits, maximumFractionDigits: digits });
            };

            function refresh() {
                var option = meter.options[meter.selectedIndex];
                if (!option || !option.value) { preview.hidden = true; return; }
                var previous = parseFloat(option.dataset.previous);
                var price = parseFloat(option.dataset.price);
                var text = {{ Js::from(__('Index précédent : ')) }} + previous.toLocaleString(document.documentElement.lang) + ' m³';
                if (index.value !== '') {
                    var consumption = Math.round((parseFloat(index.value) - previous) * 1000) / 1000;
                    var fee = parseFloat(fees[period.value] || 0);
                    text += consumption < 0
                        ? {{ Js::from(__(' — le nouvel index est inférieur à l\'index précédent.')) }}
                        : {{ Js::from(__(' — consommation ')) }} + consumption.toLocaleString(document.documentElement.lang) + ' m³ × ' + price.toLocaleString(document.documentElement.lang)
                            + (fee > 0 ? {{ Js::from(__(' + frais d\'entretien ')) }} + format(fee, decimals) : '')
                            + ' = ' + format(consumption * price + fee, decimals) + ' ' + currency;
                }
                preview.textContent = text;
                preview.hidden = false;
            }
            meter.addEventListener('change', refresh);
            period.addEventListener('change', refresh);
            index.addEventListener('input', refresh);
            refresh();
        })();
    </script>
@endpush
@endif
