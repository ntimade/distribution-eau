@extends('layouts.app')
@section('title', __('Dettes antérieures'))

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ __('Dettes antérieures') }}</h1>
            <p class="soft small" style="margin: 2px 0 0">{{ __('Ce que vos clients devaient déjà avant d\'utiliser cette application.') }}</p>
        </div>
    </div>

    <div class="stats">
        <div class="stat"><span>{{ __('Dettes reprises') }}</span><strong>{{ money($totals->amount) }}</strong></div>
        <div class="stat"><span>{{ __('Déjà payé') }}</span><strong>{{ money($totals->paid) }}</strong></div>
        <div class="stat alert"><span>{{ __('Reste à payer') }}</span><strong>{{ money($totals->amount - $totals->paid) }}</strong></div>
    </div>

    <div class="card">
        <h2>{{ __('Ajouter un mois impayé') }}</h2>
        <p class="soft small">{{ __('Saisissez une ligne par mois impayé. Les mois déjà payés ne se saisissent pas, même s\'ils sont entre deux mois impayés.') }}</p>
        @if ($meters->isEmpty())
            <p class="empty">{{ __('Aucun compteur rattaché à un client.') }} <a href="{{ route('meters.create') }}">{{ __('Ajouter un compteur') }}</a></p>
        @else
            <form method="POST" action="{{ route('opening-balances.store') }}">
                @csrf
                <div class="form-grid">
                    <x-field name="meter_id" :label="__('Client et compteur')" :required="true">
                        <select id="meter_id" name="meter_id" required>
                            <option value="">{{ __('Choisir…') }}</option>
                            @foreach ($meters->sortBy(fn ($meter) => mb_strtolower($meter->customer->name)) as $meter)
                                <option value="{{ $meter->id }}" @selected(old('meter_id') == $meter->id)>{{ $meter->customer->name }} — {{ $meter->number }}</option>
                            @endforeach
                        </select>
                    </x-field>
                    <x-field name="opening_period" :label="__('Mois impayé')" type="month" :required="true" :value="now()->subMonthNoOverflow()->format('Y-m')" max="{{ now()->format('Y-m') }}" />
                    <x-field name="opening_balance" :label="__('Montant dû pour ce mois')" type="number" step="any" min="0" :required="true" />
                </div>
                <button type="submit" class="btn">{{ __('Enregistrer ce mois') }}</button>
            </form>
        @endif
    </div>

    <div class="card">
        <h2>{{ __('Dettes enregistrées') }}</h2>
        <form class="filters" method="GET">
            <div><label for="q">{{ __('Recherche') }}</label><input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Client ou compteur') }}"></div>
            <button class="btn ghost">{{ __('Filtrer') }}</button>
        </form>
        @if ($balances->isEmpty())
            <p class="empty">{{ __('Aucune dette antérieure.') }}</p>
        @else
            <div class="table-wrap"><table id="t-dettes">
                <tr><x-sort-th :table="$balances" key="client">{{ __('Client') }}</x-sort-th><x-sort-th :table="$balances" key="meter">{{ __('Compteur') }}</x-sort-th><x-sort-th :table="$balances" key="period" kind="date">{{ __('Mois impayé') }}</x-sort-th><x-sort-th :table="$balances" key="amount" kind="number" class="right">{{ __('Montant') }}</x-sort-th><x-sort-th :table="$balances" key="paid" kind="number" class="right">{{ __('Payé') }}</x-sort-th><x-sort-th :table="$balances" key="rest" kind="number" class="right">{{ __('Reste') }}</x-sort-th><th></th></tr>
                @foreach ($balances as $balance)
                    <tr>
                        <td><a href="{{ route('customers.show', $balance->customer) }}">{{ $balance->customer->name }}</a></td>
                        <td><a href="{{ route('meters.show', $balance->meter) }}">{{ $balance->meter->number }}</a></td>
                        <td>{{ period_label($balance->period) }}</td>
                        <td class="right">{{ money($balance->amount) }}</td>
                        <td class="right">{{ money($balance->paid_amount) }}</td>
                        <td class="right @if ($balance->balance() > 0) danger-text @endif">{{ money($balance->balance()) }}</td>
                        <td>
                            <div class="actions">
                                @if ($balance->balance() > 0)
                                    <a class="btn ghost small" href="{{ route('readings.show', $balance) }}">{{ __('Encaisser') }}</a>
                                @endif
                                <a class="btn ghost small" href="{{ route('opening-balances.edit', $balance) }}">{{ __('Modifier') }}</a>
                                <form class="inline" method="POST" action="{{ route('opening-balances.destroy', $balance) }}" data-confirm="{{ __('Retirer cette dette ?') }}" onsubmit="return confirm(this.dataset.confirm)">
                                    @csrf @method('DELETE')
                                    <button class="btn danger small">{{ __('Retirer') }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </table></div>
            {{ $balances->links() }}
        @endif
    </div>
@endsection
