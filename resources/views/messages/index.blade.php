@extends('layouts.app')
@section('title', __('Messages aux clients'))

@section('content')
    <div class="page-head">
        <h1>{{ __('Messages aux clients') }}</h1>
        <x-info-button>{{ __('Chaque bouton ouvre WhatsApp ou les SMS avec le message déjà écrit : il ne reste qu\'à appuyer sur Envoyer. Pour une facture, le PDF se télécharge en même temps : joignez-le avec le trombone. Le bouton SMS apparaît sur téléphone et le SMS part de votre forfait.') }}</x-info-button>
    </div>

    <nav class="steps" aria-label="{{ __('Étapes') }}">
        @foreach ([
            ['a-envoyer', '1', __('Factures à envoyer'), __('Après le relevé')],
            ['paiements', '2', __('Confirmations de paiement'), __('Après chaque paiement')],
            ['avis', '3', __('Avis de coupure'), __('Après l\'échéance')],
        ] as [$key, $number, $label, $hint])
            <a href="{{ route('messages.index', ['etat' => $key, 'period' => $period]) }}" @class(['step', 'active' => $state === $key]) @if ($state === $key) aria-current="page" @endif>
                <span class="step-number">{{ $number }}</span>
                <span class="step-text"><strong>{{ $label }}</strong><small>{{ $hint }}</small></span>
                <span @class(['step-count', 'empty-count' => ! $counts[$key]])>{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </nav>

    @if (in_array($state, ['a-envoyer', 'ouverts', 'sans-numero'], true))
        <form class="filters" method="GET">
            <input type="hidden" name="etat" value="{{ $state }}">
            <div><label for="period">{{ __('Mois') }}</label><input id="period" type="month" name="period" value="{{ $period }}"></div>
            <button class="btn ghost">{{ __('Afficher') }}</button>
        </form>

        <p class="subnav">
            <a href="{{ route('messages.index', ['period' => $period]) }}" @if ($state === 'a-envoyer') aria-current="page" @endif>{{ __('À envoyer (:v1)', ['v1' => $counts['a-envoyer']]) }}</a>
            <a href="{{ route('messages.index', ['period' => $period, 'etat' => 'ouverts']) }}" @if ($state === 'ouverts') aria-current="page" @endif>{{ __('Déjà ouverts (:v1)', ['v1' => $counts['ouverts']]) }}</a>
            <a href="{{ route('messages.index', ['period' => $period, 'etat' => 'sans-numero']) }}" @if ($state === 'sans-numero') aria-current="page" @endif>{{ __('Sans numéro valide (:v1)', ['v1' => $counts['sans-numero']]) }}</a>
        </p>

        <div class="card">
            @if ($readings->isEmpty())
                <p class="empty">{{ $state === 'a-envoyer' ? __('Toutes les factures de ce mois sont traitées.') : __('Aucune facture ici pour ce mois.') }}</p>
            @else
                <div class="table-wrap cards-wide"><table id="t-envois">
                    <tr>
                        <x-sort-th :table="$readings" key="client">{{ __('Client') }}</x-sort-th>
                        <x-sort-th :table="$readings" key="phone">{{ __('Téléphone') }}</x-sort-th>
                        <x-sort-th :table="$readings" key="meter">{{ __('Compteur') }}</x-sort-th>
                        <x-sort-th :table="$readings" key="amount" kind="number" class="right">{{ __('Montant') }}</x-sort-th>
                        <x-sort-th :table="$readings" key="due" kind="date">{{ __('Échéance') }}</x-sort-th>
                        @if ($state === 'ouverts')<x-sort-th :table="$readings" key="opened" kind="date">{{ __('Ouvert le') }}</x-sort-th>@endif
                        <th></th>
                    </tr>
                    @foreach ($readings as $reading)
                        <tr>
                            <td><a href="{{ route('readings.show', $reading) }}">{{ $reading->customer->name }}</a></td>
                            <td>{{ $reading->customer->phone ?: '—' }}</td>
                            <td>{{ $reading->meter->number }}</td>
                            <td class="right">{{ money($reading->amount) }}</td>
                            <td>{{ $reading->due_date?->format('d/m/Y') ?? '—' }}</td>
                            @if ($state === 'ouverts')
                                <td>
                                    @if ($reading->whatsapp_opened_at)<span class="cell-line">{{ __('WhatsApp') }} {{ $reading->whatsapp_opened_at->format('d/m/Y H:i') }}</span>@endif
                                    @if ($reading->sms_opened_at)<span class="cell-line">{{ __('SMS') }} {{ $reading->sms_opened_at->format('d/m/Y H:i') }}</span>@endif
                                </td>
                            @endif
                            <td>
                                <div class="actions">
                                    @if ($state === 'sans-numero')
                                        <a class="btn ghost small" href="{{ route('customers.edit', $reading->customer) }}">{{ __('Corriger le numéro') }}</a>
                                    @else
                                        @include('messages.send', ['reading' => $reading, 'small' => true])
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </table></div>
                {{ $readings->links() }}
            @endif
        </div>
    @elseif ($state === 'paiements')
        <p class="soft small">{{ __('Les paiements des :v1 derniers jours qui n\'ont pas encore été confirmés au client.', ['v1' => \App\Http\Controllers\InvoiceMessageController::PAYMENT_DAYS]) }}</p>
        <div class="card">
            @if ($payments->isEmpty())
                <p class="empty">{{ __('Tous les paiements récents ont été confirmés.') }}</p>
            @else
                <div class="table-wrap cards-wide"><table id="t-confirmation">
                    <tr>
                        <x-sort-th :table="$payments" key="date" kind="date">{{ __('Date') }}</x-sort-th>
                        <th>{{ __('Client') }}</th>
                        <th>{{ __('Facture') }}</th>
                        <x-sort-th :table="$payments" key="amount" kind="number" class="right">{{ __('Montant') }}</x-sort-th>
                        <th></th>
                    </tr>
                    @foreach ($payments as $payment)
                        <tr>
                            <td>{{ $payment->paid_at->format('d/m/Y') }}</td>
                            <td><a href="{{ route('readings.show', $payment->reading) }}">{{ $payment->reading->customer->name }}</a></td>
                            <td>{{ $payment->reading->label() }}</td>
                            <td class="right">{{ money($payment->amount) }}</td>
                            <td><div class="actions">@include('messages.payment-send', ['payment' => $payment, 'small' => true])</div></td>
                        </tr>
                    @endforeach
                </table></div>
                {{ $payments->links() }}
            @endif
        </div>
    @else
        <p class="soft small">{{ __('Les clients dont l\'échéance est dépassée. L\'avis et sa date limite sont créés au moment de l\'envoi, pour que le client ait tout son délai.') }}</p>
        <div class="card">
            @if ($notices->isEmpty())
                <p class="empty">{{ __('Aucun avis de coupure à envoyer.') }}</p>
            @else
                <div class="table-wrap cards-wide"><table id="t-avis-envoi">
                    <tr>
                        <x-sort-th :table="$notices" key="client">{{ __('Client') }}</x-sort-th>
                        <x-sort-th :table="$notices" key="meter">{{ __('Compteur') }}</x-sort-th>
                        <x-sort-th :table="$notices" key="total" kind="number" class="right">{{ __('Total dû') }}</x-sort-th>
                        <x-sort-th :table="$notices" key="due" kind="date">{{ __('Échéance') }}</x-sort-th>
                        <th></th>
                    </tr>
                    @foreach ($notices as $row)
                        <tr>
                            <td><a href="{{ route('customers.show', $row->customer) }}">{{ $row->customer->name }}</a></td>
                            <td>{{ $row->meter->number }}</td>
                            <td class="right danger-text">{{ money($row->total) }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($row->oldest_due)->format('d/m/Y') }}</td>
                            <td><div class="actions">@include('messages.notice-send', ['customer' => $row->customer, 'meterId' => $row->meter->id])</div></td>
                        </tr>
                    @endforeach
                </table></div>
                {{ $notices->links() }}
            @endif
        </div>
    @endif
@endsection
