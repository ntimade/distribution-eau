@extends('layouts.app')
@section('title', __('Relevé'))

@section('content')
    <div class="page-head">
        <h1>{{ $reading->is_opening ? $reading->label() : __('Relevé ').of_period($reading->period) }} @include('readings.status', ['reading' => $reading])</h1>
        <div class="actions">
            <a class="btn" href="{{ route('invoices.show', $reading) }}">{{ __('Imprimer la facture') }}</a>
            @if (! $reading->is_opening && \App\Support\PhoneNumber::isInternational($reading->customer->phone))
                @include('messages.send', ['reading' => $reading])
            @endif
            @if ($reading->is_opening)
                <a class="btn ghost" href="{{ route('opening-balances.edit', $reading) }}">{{ __('Modifier cette dette') }}</a>
            @else
                <a class="btn ghost" href="{{ route('readings.edit', $reading) }}">{{ __('Corriger') }}</a>
                @if ($reading->isDeletable())
                    <form class="inline" method="POST" action="{{ route('readings.destroy', $reading) }}" data-confirm="{{ __('Supprimer ce relevé ?') }}" onsubmit="return confirm(this.dataset.confirm)">
                        @csrf @method('DELETE')
                        <button class="btn danger">{{ __('Supprimer') }}</button>
                    </form>
                @endif
            @endif
        </div>
    </div>

    @if ($justPaid = $payments->firstWhere('id', session('notify_payment')))
        @if (\App\Support\PhoneNumber::isInternational($reading->customer->phone))
            <div class="card notify-card">
                <div>
                    <strong>{{ __('Confirmez ce paiement à :v1', ['v1' => $reading->customer->name]) }}</strong>
                    <p class="soft small">{{ __('Le message est prêt, avec le montant reçu et le reste à payer.') }}</p>
                </div>
                <div class="actions">@include('messages.payment-send', ['payment' => $justPaid->setRelation('reading', $reading)])</div>
            </div>
        @endif
    @endif

    <div class="card">
        <dl class="detail">
            <div><dt>{{ __('Client') }}</dt><dd><a href="{{ route('customers.show', $reading->customer) }}">{{ $reading->customer->name }}</a></dd></div>
            <div><dt>{{ __('Compteur') }}</dt><dd><a href="{{ route('meters.show', $reading->meter) }}">{{ $reading->meter->number }}</a></dd></div>
            @unless ($reading->is_opening)
                <div><dt>{{ __('Date du relevé') }}</dt><dd>{{ $reading->read_at->format('d/m/Y') }}</dd></div>
                <div><dt>{{ __('Index précédent') }}</dt><dd>{{ qty($reading->previous_index) }} m³</dd></div>
                <div><dt>{{ __('Nouvel index') }}</dt><dd>{{ qty($reading->current_index) }} m³</dd></div>
                <div><dt>{{ __('Consommation') }}</dt><dd>{{ qty($reading->consumption) }} m³
                    @if ($reading->replacement)
                        <br><span class="soft small">{{ __('dont :v1 m³ de l\'ancien compteur :v2 (index :v3 à :v4)', ['v1' => qty($reading->carried_consumption), 'v2' => $reading->replacement->oldMeter->number, 'v3' => qty($reading->replacement->old_previous_index), 'v4' => qty($reading->replacement->old_final_index)]) }}</span>
                    @endif
                </dd></div>
                <div><dt>{{ __('Prix du m³') }}</dt><dd>{{ money($reading->unit_price) }}</dd></div>
            @endunless
            @if ((float) $reading->maintenance_fee > 0)
                <div><dt>{{ __('Frais d\'entretien') }}</dt><dd>{{ money($reading->maintenance_fee) }}</dd></div>
            @endif
            <div><dt>{{ __('Montant') }}</dt><dd>{{ money($reading->amount) }}</dd></div>
            <div><dt>{{ __('Payé') }}</dt><dd>{{ money($reading->paid_amount) }}</dd></div>
            <div><dt>{{ __('Reste à payer') }}</dt><dd @class(['danger-text' => $reading->balance() > 0])>{{ money($reading->balance()) }}</dd></div>
            <div><dt>{{ __('Échéance') }}</dt><dd>{{ $reading->due_date?->format('d/m/Y') ?? '—' }}@if ($reading->isOverdue()) <span class="badge ko">{{ __('En retard') }}</span>@endif</dd></div>
        </dl>
        @if ($reading->notes)<p class="soft small">{{ __('Observation : :v1', ['v1' => $reading->notes]) }}</p>@endif
    </div>

    @if ($reading->balance() > 0)
        <div class="card">
            <h2>{{ __('Enregistrer un paiement') }}</h2>
            <form method="POST" action="{{ route('payments.store', $reading) }}">
                @csrf
                <div class="form-grid">
                    <x-field name="amount" :label="__('Montant')" type="number" min="0" step="any" :value="$reading->balance()" :required="true" />
                    <x-field name="paid_at" :label="__('Date')" type="date" :value="now()->format('Y-m-d')" :required="true" max="{{ now()->format('Y-m-d') }}" />
                    <x-field name="method" :label="__('Mode')" list="payment-methods" :placeholder="__('Facultatif')" />
                    <x-field name="reference" :label="__('Référence')" :placeholder="__('Facultatif')" />
                </div>
                <datalist id="payment-methods">
                    <option value="{{ __('Espèces') }}"></option>
                    <option value="Mobile Money"></option>
                    <option value="Virement"></option>
                    <option value="Chèque"></option>
                </datalist>
                <button type="submit" class="btn">{{ __('Enregistrer le paiement') }}</button>
            </form>
        </div>
    @endif

    <div class="card">
        <h2>{{ __('Historique des paiements') }}</h2>
        @if ($payments->isEmpty())
            <p class="empty">{{ __('Aucun paiement.') }}</p>
        @else
            <div class="table-wrap cards-wide"><table id="t-paiements">
                <tr><x-sort-th :table="$payments" key="date" kind="date">{{ __('Date') }}</x-sort-th><x-sort-th :table="$payments" key="method">{{ __('Mode') }}</x-sort-th><x-sort-th :table="$payments" key="reference">{{ __('Référence') }}</x-sort-th><th>{{ __('Reçu') }}</th><x-sort-th :table="$payments" key="amount" kind="number" class="right">{{ __('Montant') }}</x-sort-th><th></th></tr>
                @foreach ($payments as $payment)
                    <tr>
                        <td>{{ $payment->paid_at->format('d/m/Y') }}</td>
                        <td>{{ $payment->method ?: '—' }}</td>
                        <td>{{ $payment->reference ?: '—' }}</td>
                        <td>@if ($payment->receipt)<a href="{{ route('receipts.show', $payment->receipt) }}">{{ $payment->receipt->number() }}</a>@else — @endif</td>
                        <td class="right">{{ money($payment->amount) }}</td>
                        <td>
                            <div class="actions">
                                @include('messages.payment-send', ['payment' => $payment->setRelation('reading', $reading), 'small' => true])
                                <a class="btn ghost small" href="{{ route('payments.edit', $payment) }}">{{ __('Corriger') }}</a>
                                <form class="inline" method="POST" action="{{ route('payments.destroy', $payment) }}" data-confirm="{{ __('Annuler ce paiement ?') }}" onsubmit="return confirm(this.dataset.confirm)">
                                    @csrf @method('DELETE')
                                    <button class="btn danger small">{{ __('Annuler') }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </table></div>
            {{ $payments->links() }}
        @endif
    </div>
@endsection
