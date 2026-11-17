@extends('layouts.app')
@section('title', __('Encaissements'))

@section('content')
    <div class="page-head">
        <h1>{{ __('Encaissements') }}</h1>
        <div class="actions"><a class="btn" href="{{ route('receipts.create') }}"><x-icon name="wallet" /> {{ __('Nouvel encaissement') }}</a></div>
    </div>
    <p class="soft small">{{ __('Encaissez en une fois plusieurs mois choisis librement par le client, sur un ou plusieurs de ses compteurs. Chaque mois est mis à jour et un reçu récapitule l\'encaissement.') }}</p>

    <div class="card">
        <form class="filters" method="GET">
            <div>
                <label for="customer_id">{{ __('Client') }}</label>
                <select id="customer_id" name="customer_id">
                    <option value="">{{ __('Tous') }}</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected((string) request('customer_id') === (string) $customer->id)>{{ $customer->name }}</option>
                    @endforeach
                </select>
            </div>
            <div><label for="from">{{ __('Du') }}</label><input id="from" type="date" name="from" value="{{ request('from') }}"></div>
            <div><label for="to">{{ __('Au') }}</label><input id="to" type="date" name="to" value="{{ request('to') }}"></div>
            <button class="btn ghost">{{ __('Filtrer') }}</button>
        </form>

        <p><strong>{{ __(':v1 encaissement(s) · Total : :v2', ['v1' => $count, 'v2' => money($total)]) }}</strong></p>

        @if ($receipts->isEmpty())
            <p class="empty">{{ __('Aucun encaissement.') }}</p>
        @else
            <div class="table-wrap cards-wide"><table id="t-encaissements">
                <tr><th>{{ __('Reçu') }}</th><x-sort-th :table="$receipts" key="date" kind="date">{{ __('Date') }}</x-sort-th><x-sort-th :table="$receipts" key="client">{{ __('Client') }}</x-sort-th><th>{{ __('Mois réglés') }}</th><th>{{ __('Mode') }}</th><x-sort-th :table="$receipts" key="amount" kind="number" class="right">{{ __('Montant') }}</x-sort-th></tr>
                @foreach ($receipts as $receipt)
                    <tr>
                        <td><a href="{{ route('receipts.show', $receipt) }}">{{ $receipt->number() }}</a></td>
                        <td>{{ $receipt->paid_at->format('d/m/Y') }}</td>
                        <td>{{ $receipt->customer->name }}</td>
                        <td>
                            @foreach ($receipt->payments->groupBy(fn ($payment) => $payment->reading->meter->number)->sortKeys() as $number => $payments)
                                <span class="cell-line">{{ __('Compteur :v1', ['v1' => $number]) }} : {{ $payments->sortBy(fn ($payment) => $payment->reading->period)->map(fn ($payment) => $payment->reading->label())->implode(', ') }}</span>
                            @endforeach
                        </td>
                        <td>{{ $receipt->method ?: '—' }}</td>
                        <td class="right">{{ money($receipt->total) }}</td>
                    </tr>
                @endforeach
            </table></div>
            {{ $receipts->links() }}
        @endif
    </div>
@endsection
