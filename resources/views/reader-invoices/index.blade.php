@extends('layouts.app')
@section('title', __('Factures impayées'))

@section('content')
    <div class="page-head">
        <h1>{{ __('Factures impayées') }}</h1>
        <x-info-button>{{ __('Chaque bouton ouvre WhatsApp ou les SMS avec le message déjà écrit : il ne reste qu\'à appuyer sur Envoyer. Le PDF se télécharge en même temps : joignez-le avec le trombone.') }}</x-info-button>
    </div>

    <form class="filters" method="GET">
        <div><label for="q">{{ __('Rechercher') }}</label><input id="q" type="search" name="q" value="{{ $search }}" placeholder="{{ __('Client ou compteur') }}"></div>
        <button class="btn ghost">{{ __('Afficher') }}</button>
    </form>

    <div class="card">
        @if ($readings->isEmpty())
            <p class="empty">{{ __('Aucune facture impayée ici.') }}</p>
        @else
            <div class="table-wrap cards-wide"><table id="t-factures">
                <tr>
                    <x-sort-th :table="$readings" key="client">{{ __('Client') }}</x-sort-th>
                    <x-sort-th :table="$readings" key="meter">{{ __('Compteur') }}</x-sort-th>
                    <th>{{ __('Mois') }}</th>
                    <x-sort-th :table="$readings" key="rest" kind="number" class="right">{{ __('Reste à payer') }}</x-sort-th>
                    <x-sort-th :table="$readings" key="due" kind="date">{{ __('Échéance') }}</x-sort-th>
                    <th></th>
                </tr>
                @foreach ($readings as $reading)
                    <tr>
                        <td>{{ $reading->customer->name }}</td>
                        <td>{{ $reading->meter->number }}</td>
                        <td>{{ period_label($reading->period) }}</td>
                        <td class="right">{{ money($reading->balance()) }}</td>
                        <td>{{ $reading->due_date?->format('d/m/Y') ?? '—' }}@if ($reading->isOverdue()) <span class="badge ko">{{ __('En retard') }}</span>@endif</td>
                        <td>
                            <div class="actions">
                                @if (\App\Support\PhoneNumber::isInternational($reading->customer->phone))
                                    @include('messages.send', ['reading' => $reading, 'small' => true])
                                @else
                                    <span class="soft small">{{ __('Numéro absent ou à corriger : prévenez l\'administrateur.') }}</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </table></div>
            {{ $readings->links() }}
        @endif
    </div>
@endsection
