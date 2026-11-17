@extends('layouts.app')
@section('title', __('Nouvel encaissement'))

@section('content')
    <div class="page-head">
        <h1>{{ $customer ? __('Encaisser — :v1', ['v1' => $customer->name]) : __('Nouvel encaissement') }}</h1>
        <div class="actions"><a class="btn ghost" href="{{ $customer ? route('customers.show', $customer) : route('receipts.index') }}">{{ __('Retour') }}</a></div>
    </div>

    @unless ($customer)
        {{-- Étape 1 : choisir le client parmi ceux qui ont des mois impayés. --}}
        <div class="card">
            <p class="soft small">{{ __('Choisissez le client. Sa dette s\'affiche ensuite compteur par compteur : le montant versé paie d\'abord les mois les plus anciens.') }}</p>
            @if ($customers->isEmpty())
                <p class="empty">{{ __('Aucun client n\'a de mois impayé.') }}</p>
            @else
                <div class="field"><label for="customer-search">{{ __('Recherche') }}</label><input id="customer-search" type="search" placeholder="{{ __('Nom ou téléphone') }}" autocomplete="off"></div>
                <div class="table-wrap cards-wide"><table id="t-clients-dettes">
                    <tr><th>{{ __('Client') }}</th><th class="right">{{ __('Mois impayés') }}</th><th class="right">{{ __('Total dû') }}</th><th></th></tr>
                    @foreach ($customers as $row)
                        <tr data-search="{{ mb_strtolower($row->name.' '.$row->phone) }}">
                            <td>{{ $row->name }}@if ($row->phone)<span class="soft small cell-line">{{ $row->phone }}</span>@endif</td>
                            <td class="right">{{ $row->unpaid_months }}</td>
                            <td class="right danger-text">{{ money($row->debt) }}</td>
                            <td><a class="btn small" href="{{ route('receipts.create', ['customer' => $row->id]) }}"><x-icon name="wallet" /> {{ __('Encaisser') }}</a></td>
                        </tr>
                    @endforeach
                </table></div>
            @endif
        </div>
    @else
        @if ($groups->isEmpty())
            <div class="card"><p class="empty">{{ __('Ce client n\'a aucun mois impayé.') }}</p></div>
        @elseif ($chooseMeter)
            {{-- Étape 2 : le client a plusieurs compteurs endettés, on choisit celui à encaisser. --}}
            <div class="card">
                <h2>{{ __('Quel compteur encaisser ?') }}</h2>
                <p class="soft small">{{ __('Ce client a une dette sur plusieurs compteurs. Choisissez le compteur : le montant versé paiera ses mois du plus ancien au plus récent.') }}</p>
                <div class="table-wrap"><table id="t-choix-compteur">
                    <tr><th>{{ __('Compteur') }}</th><th class="right">{{ __('Mois impayés') }}</th><th class="right">{{ __('Reste dû') }}</th><th></th></tr>
                    @foreach ($groups as $group)
                        <tr>
                            <td>{{ $group->meter->number }}@if ($group->meter->zone)<span class="soft small cell-line">{{ $group->meter->zone->name }}</span>@endif</td>
                            <td class="right">{{ $group->readings->count() }}</td>
                            <td class="right danger-text">{{ money($group->total) }}</td>
                            <td><a class="btn small" href="{{ route('receipts.create', ['customer' => $customer->id, 'meter' => $group->meter->id]) }}"><x-icon name="wallet" /> {{ __('Encaisser ce compteur') }}</a></td>
                        </tr>
                    @endforeach
                </table></div>
                <p><a class="btn ghost" href="{{ route('receipts.create', ['customer' => $customer->id, 'tous' => 1]) }}">{{ __('Tous les compteurs ensemble (:v1)', ['v1' => money($groups->sum('total'))]) }}</a></p>
            </div>
        @else
            @php
                // L'ordre de paiement : du mois le plus ancien au plus récent, tous compteurs confondus
                // (à mois égal, le compteur au plus petit numéro d'abord) — le même que celui du serveur.
                $ordre = $groups->flatMap(fn ($group) => $group->readings)
                    ->sortBy(fn ($reading) => [$reading->period, $reading->meter->number, $reading->id])
                    ->values()->mapWithKeys(fn ($reading, $rang) => [$reading->id => $rang]);
                $dette = round((float) $groups->sum('total'), 3);
            @endphp
            <form method="POST" action="{{ route('receipts.store', $customer) }}" id="receipt-form">
                @csrf
                @if ($meter)
                    <input type="hidden" name="meter_id" value="{{ $meter->id }}">
                    <p class="soft small">{{ __('Seule la dette du compteur :v1 est réglée.', ['v1' => $meter->number]) }} <a href="{{ route('receipts.create', ['customer' => $customer->id]) }}">{{ __('Choisir un autre compteur') }}</a></p>
                @endif

                <div class="card">
                    <h2>{{ __('Montant versé') }}</h2>
                    <p class="soft small">{{ __('Le montant versé paie d\'abord la dette la plus ancienne, puis les mois suivants. Laissez le total pour tout régler, ou saisissez moins pour une avance.') }}</p>
                    <div class="form-grid">
                        <x-field name="amount" :label="__('Montant versé')" type="number" min="0" step="any" :max="$dette" :value="$dette" :required="true" />
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
                    <p class="receipt-total">
                        <strong>{{ __('Dette totale') }} : {{ money($dette) }}</strong>
                        · {{ __('Reste après ce versement') }} : <strong id="receipt-left">{{ money(0) }}</strong>
                    </p>
                    <button type="submit" class="btn">{{ __('Encaisser') }}</button>
                </div>

                @foreach ($groups as $group)
                    <div class="card" data-meter-group>
                        <h2>{{ __('Compteur :v1', ['v1' => $group->meter->number]) }}</h2>
                        <div class="table-wrap"><table>
                            <tr><th>{{ __('Mois') }}</th><th>{{ __('Facture') }}</th><th class="right">{{ __('Reste dû') }}</th><th class="right">{{ __('Payé par ce versement') }}</th><th class="right">{{ __('Reste après') }}</th></tr>
                            @foreach ($group->readings as $reading)
                                <tr data-line data-order="{{ $ordre[$reading->id] }}" data-balance="{{ $reading->balance() }}">
                                    <td>{{ $reading->label() }}@if ($reading->isOverdue()) <span class="badge partial">{{ __('En retard') }}</span>@endif</td>
                                    <td>{{ $reading->invoiceNumber() }}</td>
                                    <td class="right danger-text">{{ money($reading->balance()) }}</td>
                                    <td class="right" data-pay>—</td>
                                    <td class="right" data-rest>—</td>
                                </tr>
                            @endforeach
                        </table></div>
                        <p class="right">{{ __('Dette du compteur : :v1', ['v1' => money($group->total)]) }} · <strong>{{ __('Encaissé sur ce compteur') }} : <span data-meter-total>0</span></strong></p>
                    </div>
                @endforeach
            </form>
        @endif
    @endunless
@endsection

@push('scripts')
    <script>
        (function () {
            var search = document.getElementById('customer-search');
            if (search) {
                search.addEventListener('input', function () {
                    var term = search.value.trim().toLowerCase();
                    document.querySelectorAll('#t-clients-dettes tr[data-search]').forEach(function (row) {
                        row.hidden = term !== '' && row.dataset.search.indexOf(term) === -1;
                    });
                });
            }

            var form = document.getElementById('receipt-form');
            if (!form) { return; }
            var currency = {{ Js::from(auth()->user()->organisation->currency) }};
            var decimals = {{ (int) auth()->user()->organisation->currencyDecimals() }};
            var amountInput = form.querySelector('[name="amount"]');
            var paidLabel = {{ Js::from(__('Payé')) }};

            function money(value) {
                return value.toLocaleString('fr-FR', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }) + ' ' + currency;
            }

            // Aperçu de la répartition : le montant versé paie les mois du plus ancien au plus récent.
            function refresh() {
                var left = Math.max(0, parseFloat(amountInput.value) || 0);
                var lines = Array.prototype.slice.call(form.querySelectorAll('[data-line]'))
                    .sort(function (a, b) { return Number(a.dataset.order) - Number(b.dataset.order); });
                var debtLeft = 0;
                lines.forEach(function (row) {
                    var balance = parseFloat(row.dataset.balance);
                    var pay = Math.min(left, balance);
                    left = Math.round((left - pay) * 1000) / 1000;
                    var rest = Math.round((balance - pay) * 1000) / 1000;
                    debtLeft += rest;
                    row.dataset.paid = pay;
                    row.querySelector('[data-pay]').textContent = pay > 0 ? money(pay) : '—';
                    row.querySelector('[data-rest]').textContent = rest > 0 ? money(rest) : paidLabel;
                    row.classList.toggle('row-paid', rest <= 0);
                });
                form.querySelectorAll('[data-meter-group]').forEach(function (group) {
                    var sum = 0;
                    group.querySelectorAll('[data-line]').forEach(function (row) { sum += parseFloat(row.dataset.paid) || 0; });
                    group.querySelector('[data-meter-total]').textContent = money(sum);
                });
                document.getElementById('receipt-left').textContent = money(debtLeft);
            }

            amountInput.addEventListener('input', refresh);
            refresh();
        })();
    </script>
@endpush
