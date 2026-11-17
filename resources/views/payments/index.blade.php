@extends('layouts.app')
@section('title', __('Paiements'))

@section('content')
    <div class="page-head"><h1>{{ __('Historique des paiements') }}</h1></div>

    <div class="card">
        <form class="filters" method="GET">
            <div>
                <label for="customer_id">{{ __('Client') }}</label>
                <select id="customer_id" name="customer_id">
                    <option value="">{{ __('Tous') }}</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected(request('customer_id') == $customer->id)>{{ $customer->name }}</option>
                    @endforeach
                </select>
            </div>
            <div><label for="from">{{ __('Du') }}</label><input id="from" type="date" name="from" value="{{ request('from') }}"></div>
            <div><label for="to">{{ __('Au') }}</label><input id="to" type="date" name="to" value="{{ request('to') }}"></div>
            <button class="btn ghost">{{ __('Filtrer') }}</button>
        </form>

        <p><strong>{{ __('Total : :v1', ['v1' => money($total)]) }}</strong></p>

        @if ($payments->isEmpty())
            <p class="empty">{{ __('Aucun paiement.') }}</p>
        @else
            <div class="table-wrap cards-wide"><table id="t-paiements">
                <tr><x-sort-th :table="$payments" key="date" kind="date">{{ __('Date') }}</x-sort-th><x-sort-th :table="$payments" key="client">{{ __('Client') }}</x-sort-th><x-sort-th :table="$payments" key="meter">{{ __('Compteur') }}</x-sort-th><x-sort-th :table="$payments" key="period" kind="date">{{ __('Mois facturé') }}</x-sort-th><x-sort-th :table="$payments" key="method">{{ __('Mode') }}</x-sort-th><x-sort-th :table="$payments" key="reference">{{ __('Référence') }}</x-sort-th><th>{{ __('Reçu') }}</th><x-sort-th :table="$payments" key="amount" kind="number" class="right">{{ __('Montant') }}</x-sort-th><th></th></tr>
                @foreach ($payments as $payment)
                    <tr>
                        <td>{{ $payment->paid_at->format('d/m/Y') }}</td>
                        <td><a href="{{ route('customers.show', $payment->reading->customer) }}">{{ $payment->reading->customer->name }}</a></td>
                        <td>{{ $payment->reading->meter->number }}</td>
                        <td><a href="{{ route('readings.show', $payment->reading) }}">{{ $payment->reading->label() }}</a></td>
                        <td>{{ $payment->method ?: '—' }}</td>
                        <td>{{ $payment->reference ?: '—' }}</td>
                        <td>@if ($payment->receipt)<a href="{{ route('receipts.show', $payment->receipt) }}">{{ $payment->receipt->number() }}</a>@else — @endif</td>
                        <td class="right">{{ money($payment->amount) }}</td>
                        <td><div class="actions">@include('messages.payment-send', ['payment' => $payment, 'small' => true])</div></td>
                    </tr>
                @endforeach
            </table></div>
            {{ $payments->links() }}
        @endif
    </div>
@endsection
