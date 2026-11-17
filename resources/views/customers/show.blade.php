@extends('layouts.app')
@section('title', $customer->name)

@section('content')
    <div class="page-head">
        <h1>{{ $customer->name }}</h1>
        <div class="actions">
            <a class="btn ghost" href="{{ route('customers.edit', $customer) }}">{{ __('Modifier') }}</a>
            <form class="inline" method="POST" action="{{ route('customers.destroy', $customer) }}" data-confirm="{{ __('Supprimer ce client ?') }}" onsubmit="return confirm(this.dataset.confirm)">
                @csrf @method('DELETE')
                <button class="btn danger">{{ __('Supprimer') }}</button>
            </form>
        </div>
    </div>

    <div class="card">
        <dl class="detail">
            <div><dt>{{ __('Téléphone') }}</dt><dd>{{ $customer->phone ?: '—' }}</dd></div>
            <div><dt>{{ __('E-mail') }}</dt><dd>{{ $customer->email ?: '—' }}</dd></div>
            <div><dt>{{ __('Adresse') }}</dt><dd>{{ $customer->address ?: '—' }}</dd></div>
            <div><dt>{{ __('Prix du m³') }}</dt><dd>{{ $customer->price_per_m3 === null ? __('Tarif général') : money($customer->price_per_m3) }}</dd></div>
            <div><dt>{{ __('Impayés') }}</dt><dd @class(['danger-text' => $unpaid > 0])>{{ money($unpaid) }}</dd></div>
        </dl>
    </div>

    <div class="card">
        <div class="row-between">
            <h2>{{ __('Dettes par compteur') }}</h2>
            @if ($debts->isNotEmpty())
                <a class="btn" href="{{ route('receipts.create', ['customer' => $customer->id]) }}"><x-icon name="wallet" /> {{ __('Encaisser plusieurs mois') }}</a>
            @endif
        </div>
        @if ($debts->isEmpty())
            <p class="empty">{{ __('Aucun mois impayé : tous les compteurs de ce client sont à jour.') }}</p>
        @else
            <div class="table-wrap"><table id="t-dettes-compteurs">
                <tr><th>{{ __('Compteur') }}</th><th class="right">{{ __('Mois impayés') }}</th><th class="right">{{ __('Reste dû') }}</th><th></th></tr>
                @foreach ($debts as $debt)
                    <tr>
                        <td><a href="{{ route('meters.show', $debt->meter) }}">{{ $debt->meter->number }}</a></td>
                        <td class="right">{{ $debt->months }}</td>
                        <td class="right danger-text">{{ money($debt->total) }}</td>
                        <td><a class="btn ghost small" href="{{ route('receipts.create', ['customer' => $customer->id, 'meter' => $debt->meter_id]) }}">{{ __('Encaisser ce compteur') }}</a></td>
                    </tr>
                @endforeach
            </table></div>
        @endif
    </div>

    @if ($receipts->isNotEmpty())
        <div class="card">
            <div class="row-between">
                <h2>{{ __('Encaissements') }}</h2>
                <a class="btn ghost small" href="{{ route('receipts.index', ['customer_id' => $customer->id]) }}">{{ __('Tout l\'historique') }}</a>
            </div>
            <div class="table-wrap"><table id="t-encaissements-client">
                <tr><th>{{ __('Reçu') }}</th><th>{{ __('Date') }}</th><th>{{ __('Mois réglés') }}</th><th class="right">{{ __('Montant') }}</th></tr>
                @foreach ($receipts as $receipt)
                    <tr>
                        <td><a href="{{ route('receipts.show', $receipt) }}">{{ $receipt->number() }}</a></td>
                        <td>{{ $receipt->paid_at->format('d/m/Y') }}</td>
                        <td>
                            @foreach ($receipt->payments->groupBy(fn ($payment) => $payment->reading->meter->number)->sortKeys() as $number => $payments)
                                <span class="cell-line">{{ __('Compteur :v1', ['v1' => $number]) }} : {{ $payments->sortBy(fn ($payment) => $payment->reading->period)->map(fn ($payment) => $payment->reading->label())->implode(', ') }}</span>
                            @endforeach
                        </td>
                        <td class="right">{{ money($receipt->total) }}</td>
                    </tr>
                @endforeach
            </table></div>
        </div>
    @endif

    <div class="card">
        <div class="row-between">
            <h2>{{ __('Compteurs') }}</h2>
            <a class="btn ghost small" href="{{ route('meters.create', ['customer_id' => $customer->id]) }}">{{ __('Ajouter un compteur') }}</a>
        </div>
        @if ($meters->isEmpty())
            <p class="empty">{{ __('Aucun compteur rattaché.') }}</p>
        @else
            <div class="table-wrap"><table id="t-compteurs">
                <tr><x-sort-th :table="$meters" key="number">{{ __('Numéro') }}</x-sort-th><x-sort-th :table="$meters" key="zone">{{ __('Zone') }}</x-sort-th><x-sort-th :table="$meters" key="active">{{ __('État') }}</x-sort-th></tr>
                @foreach ($meters as $meter)
                    <tr>
                        <td><a href="{{ route('meters.show', $meter) }}">{{ $meter->number }}</a></td>
                        <td>{{ $meter->zone?->name ?? '—' }}</td>
                        <td><span class="badge {{ $meter->is_active ? 'ok' : 'off' }}">{{ $meter->is_active ? __('Actif') : __('Désactivé') }}</span></td>
                    </tr>
                @endforeach
            </table></div>
            {{ $meters->links() }}
        @endif
    </div>

    <div class="card">
        <h2>{{ __('Factures et paiements') }}</h2>
        @if ($readings->isEmpty())
            <p class="empty">{{ __('Aucun relevé pour ce client.') }}</p>
        @else
            <div class="table-wrap"><table id="t-factures">
                <tr><x-sort-th :table="$readings" key="period" kind="date">{{ __('Mois') }}</x-sort-th><x-sort-th :table="$readings" key="meter">{{ __('Compteur') }}</x-sort-th><x-sort-th :table="$readings" key="consumption" kind="number" class="right">{{ __('Conso.') }}</x-sort-th><x-sort-th :table="$readings" key="amount" kind="number" class="right">{{ __('Montant') }}</x-sort-th><x-sort-th :table="$readings" key="paid" kind="number" class="right">{{ __('Payé') }}</x-sort-th><x-sort-th :table="$readings" key="rest" kind="number" class="right">{{ __('Reste') }}</x-sort-th><x-sort-th :table="$readings" key="due" kind="date">{{ __('Échéance') }}</x-sort-th><th></th></tr>
                @foreach ($readings as $reading)
                    <tr>
                        <td><a href="{{ route('readings.show', $reading) }}">{{ $reading->label() }}</a></td>
                        <td>{{ $reading->meter->number }}</td>
                        <td class="right">{{ qty($reading->consumption) }} m³</td>
                        <td class="right">{{ money($reading->amount) }}</td>
                        <td class="right">{{ money($reading->paid_amount) }}</td>
                        <td class="right @if ($reading->balance() > 0) danger-text @endif">{{ money($reading->balance()) }}</td>
                        <td>{{ $reading->due_date?->format('d/m/Y') ?? '—' }}</td>
                        <td>@include('readings.status', ['reading' => $reading])</td>
                    </tr>
                @endforeach
            </table></div>
            {{ $readings->links() }}
        @endif
    </div>
@endsection
