@extends('layouts.app')
@section('title', __('Reçu :v1', ['v1' => $receipt->number()]))

@section('content')
    <div class="page-head">
        <h1>{{ __('Reçu :v1', ['v1' => $receipt->number()]) }}</h1>
        <div class="actions">
            <a class="btn" href="{{ route('receipts.print', $receipt) }}"><x-icon name="receipt" /> {{ __('Imprimer le reçu') }}</a>
            <a class="btn ghost" href="{{ route('receipts.pdf', $receipt) }}" download><x-icon name="download" /> {{ __('PDF') }}</a>
        </div>
    </div>

    <div class="card">
        <h2>{{ __('Confirmer au client') }}</h2>
        @if (\App\Support\PhoneNumber::isInternational($receipt->customer->phone))
            <div class="actions">
                <form class="inline" method="POST" action="{{ route('receipts.message', [$receipt, 'whatsapp']) }}" target="_blank" rel="noopener" data-message-send>
                    @csrf
                    <button @class(['btn', 'ghost' => $receipt->whatsapp_opened_at])><x-icon name="send" /> {{ __('Envoyer le reçu sur WhatsApp') }}</button>
                </form>
                <form class="inline" method="POST" action="{{ route('receipts.message', [$receipt, 'sms']) }}" data-message-send>
                    @csrf
                    <button class="btn ghost"><x-icon name="message-square" /> {{ __('Par SMS') }}</button>
                </form>
            </div>
        @else
            <p class="soft">{{ __('Le numéro de ce client est absent ou à corriger : le message ne peut pas être préparé.') }}</p>
        @endif
    </div>

    <div class="card">
        <dl class="detail">
            <div><dt>{{ __('Client') }}</dt><dd><a href="{{ route('customers.show', $receipt->customer) }}">{{ $receipt->customer->name }}</a></dd></div>
            <div><dt>{{ __('Date') }}</dt><dd>{{ $receipt->paid_at->format('d/m/Y') }}</dd></div>
            <div><dt>{{ __('Mode') }}</dt><dd>{{ $receipt->method ?: '—' }}</dd></div>
            <div><dt>{{ __('Référence') }}</dt><dd>{{ $receipt->reference ?: '—' }}</dd></div>
            <div><dt>{{ __('Total encaissé') }}</dt><dd><strong>{{ money($receipt->total) }}</strong></dd></div>
            <div><dt>{{ __('Encaissé par') }}</dt><dd>{{ $receipt->user?->name ?? '—' }}, {{ $receipt->created_at->format('d/m/Y H:i') }}</dd></div>
        </dl>
    </div>

    @foreach ($groups as $group)
        <div class="card">
            <div class="row-between">
                <h2><a href="{{ route('meters.show', $group->meter) }}">{{ __('Compteur :v1', ['v1' => $group->meter->number]) }}</a></h2>
                <span @class(['badge', 'ok' => $group->remaining <= 0, 'partial' => $group->remaining > 0])>{{ $group->remaining > 0 ? __('Reste dû : :v1', ['v1' => money($group->remaining)]) : __('À jour') }}</span>
            </div>
            <div class="table-wrap"><table>
                <tr><th>{{ __('Mois') }}</th><th>{{ __('Facture') }}</th><th class="right">{{ __('Montant de la facture') }}</th><th class="right">{{ __('Payé par ce reçu') }}</th><th class="right">{{ __('Reste sur la facture') }}</th></tr>
                @foreach ($group->payments as $payment)
                    <tr>
                        <td><a href="{{ route('readings.show', $payment->reading) }}">{{ $payment->reading->label() }}</a></td>
                        <td>{{ $payment->reading->invoiceNumber() }}</td>
                        <td class="right">{{ money($payment->reading->amount) }}</td>
                        <td class="right">{{ money($payment->amount) }}</td>
                        <td class="right">@if ($payment->reading->balance() > 0)<span class="danger-text">{{ money($payment->reading->balance()) }}</span>@else<span class="badge ok">{{ __('Payée') }}</span>@endif</td>
                    </tr>
                @endforeach
                <tr><td colspan="3"><strong>{{ __('Sous-total') }}</strong></td><td class="right"><strong>{{ money($group->subtotal) }}</strong></td><td></td></tr>
            </table></div>
        </div>
    @endforeach

    <div class="card">
        <h2>{{ __('Erreur de saisie ?') }}</h2>
        <p class="soft small">{{ __('Annuler l\'encaissement annule d\'un coup tous les mois qu\'il a réglés : ils redeviennent à payer.') }}</p>
        <form method="POST" action="{{ route('receipts.destroy', $receipt) }}" data-confirm="{{ __('Annuler cet encaissement et tous ses paiements ?') }}" onsubmit="return confirm(this.dataset.confirm)">
            @csrf @method('DELETE')
            <button class="btn danger">{{ __('Annuler l\'encaissement') }}</button>
        </form>
    </div>
@endsection
