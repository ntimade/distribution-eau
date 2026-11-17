@extends('layouts.app')
@section('title', __('Relevés'))

@section('content')
    <div class="page-head">
        <h1>{{ __('Relevés — :v1', ['v1' => period_label($period)]) }}</h1>
        <div class="actions">
            @if ($readings->isNotEmpty())<a class="btn ghost" href="{{ route('messages.index', ['period' => $period]) }}">{{ __('Envoyer les messages') }}</a><a class="btn ghost" href="{{ route('invoices.month', ['period' => $period]) }}">{{ __('Imprimer les factures du mois') }}</a>@endif
            <a class="btn" href="{{ route('readings.create', ['period' => $period]) }}">{{ __('Saisir un relevé') }}</a>
        </div>
    </div>

    @if ($justSaved)
        <div class="card notify-card">
            @if (\App\Support\PhoneNumber::isInternational($justSaved->customer->phone))
                <div>
                    <strong>{{ __('Envoyez sa facture à :v1', ['v1' => $justSaved->customer->name]) }}</strong>
                    <p class="soft small">{{ __('Le message est prêt, avec le montant et la date limite de paiement.') }}</p>
                </div>
                <div class="actions">@include('messages.send', ['reading' => $justSaved])</div>
            @else
                <div>
                    <strong>{{ __('Impossible d\'envoyer la facture à :v1', ['v1' => $justSaved->customer->name]) }}</strong>
                    <p class="soft small">{{ __('Son numéro de téléphone est absent ou à corriger.') }}</p>
                </div>
                <a class="btn ghost" href="{{ route('customers.edit', $justSaved->customer) }}">{{ __('Corriger le numéro') }}</a>
            @endif
        </div>
    @endif

    <form class="filters" method="GET">
        <div><label for="period">{{ __('Mois') }}</label><input id="period" type="month" name="period" value="{{ $period }}"></div>
        <button class="btn ghost">{{ __('Afficher') }}</button>
    </form>

    <div class="stats">
        <div class="stat"><span>{{ __('Volume relevé') }}</span><strong>{{ qty($totals->volume) }} m³</strong></div>
        <div class="stat"><span>{{ __('Montant facturé') }}</span><strong>{{ money($totals->billed) }}</strong></div>
        <div class="stat"><span>{{ __('Compteurs à relever') }}</span><strong>{{ $pendingMeters->count() }}</strong></div>
    </div>

    @if ($pendingMeters->isNotEmpty())
        <div class="card">
            <h2>{{ __('À relever ce mois-ci') }}</h2>
            <div class="table-wrap"><table id="t-attente">
                <tr><x-sort-th :table="$pendingMeters" key="number">{{ __('Compteur') }}</x-sort-th><x-sort-th :table="$pendingMeters" key="client">{{ __('Client') }}</x-sort-th><th></th></tr>
                @foreach ($pendingMeters as $meter)
                    <tr>
                        <td>{{ $meter->number }}</td>
                        <td>{{ $meter->customer->name }}</td>
                        <td><div class="actions"><a class="btn ghost small" href="{{ route('readings.create', ['meter_id' => $meter->id, 'period' => $period]) }}">{{ __('Saisir') }}</a></div></td>
                    </tr>
                @endforeach
            </table></div>
            {{ $pendingMeters->links() }}
        </div>
    @endif

    <div class="card">
        <h2>{{ __('Relevés saisis') }}</h2>
        @if ($readings->isEmpty())
            <p class="empty">{{ __('Aucun relevé pour ce mois.') }}</p>
        @else
            <div class="table-wrap"><table id="t-releves">
                <tr><x-sort-th :table="$readings" key="meter">{{ __('Compteur') }}</x-sort-th><x-sort-th :table="$readings" key="client">{{ __('Client') }}</x-sort-th><x-sort-th :table="$readings" key="previous" kind="number" class="right">{{ __('Index précédent') }}</x-sort-th><x-sort-th :table="$readings" key="current" kind="number" class="right">{{ __('Nouvel index') }}</x-sort-th><x-sort-th :table="$readings" key="consumption" kind="number" class="right">{{ __('Conso.') }}</x-sort-th><x-sort-th :table="$readings" key="maintenance" kind="number" class="right">{{ __('Entretien') }}</x-sort-th><x-sort-th :table="$readings" key="amount" kind="number" class="right">{{ __('Montant') }}</x-sort-th><x-sort-th :table="$readings" key="due" kind="date">{{ __('Échéance') }}</x-sort-th><th></th></tr>
                @foreach ($readings as $reading)
                    <tr>
                        <td><a href="{{ route('readings.show', $reading) }}">{{ $reading->meter->number }}</a></td>
                        <td>{{ $reading->customer->name }}</td>
                        <td class="right">{{ qty($reading->previous_index) }}</td>
                        <td class="right">{{ qty($reading->current_index) }}</td>
                        <td class="right">{{ qty($reading->consumption) }} m³@if ((float) $reading->carried_consumption > 0)<br><span class="soft small">{{ __('dont :v1 ancien compteur', ['v1' => qty($reading->carried_consumption)]) }}</span>@endif</td>
                        <td class="right">{{ (float) $reading->maintenance_fee > 0 ? money($reading->maintenance_fee) : '—' }}</td>
                        <td class="right">{{ money($reading->amount) }}</td>
                        <td>{{ $reading->due_date?->format('d/m/Y') ?? '—' }}</td>
                        <td>@include('readings.status', ['reading' => $reading])</td>
                    </tr>
                @endforeach
            </table></div>
            {{ $readings->links() }}
        @endif
    </div>
@endsection
