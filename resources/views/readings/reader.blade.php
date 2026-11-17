@extends('layouts.app')
@section('title', __('Relevés'))

@section('content')
    <div class="page-head"><h1>{{ __('Relevés — :v1', ['v1' => period_label($period)]) }}</h1></div>

    @if ($errors->any())
        <div class="flash error">{{ $errors->first() }}</div>
    @endif

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
                    <p class="soft small">{{ __('Numéro absent ou à corriger : prévenez l\'administrateur.') }}</p>
                </div>
            @endif
        </div>
    @endif

    <form class="filters" method="GET">
        <div><label for="period">{{ __('Mois') }}</label><input id="period" type="month" name="period" value="{{ $period }}" max="{{ now()->format('Y-m') }}"></div>
        <div><label for="q">{{ __('Rechercher') }}</label><input id="q" type="search" name="q" value="{{ $search }}" placeholder="{{ __('Client ou compteur') }}"></div>
        <button class="btn ghost">{{ __('Afficher') }}</button>
    </form>

    <p class="soft small">{{ __(':v1 compteur(s) à relever sur :v2.', ['v1' => $pendingCount, 'v2' => $meters->total()]) }}</p>

    <div class="card">
        @if ($meters->isEmpty())
            <p class="empty">{{ __('Aucun compteur à afficher.') }}</p>
        @else
            <div class="table-wrap cards-wide"><table id="t-compteurs">
                <tr>
                    <x-sort-th :table="$meters" key="number">{{ __('Compteur') }}</x-sort-th>
                    <x-sort-th :table="$meters" key="client">{{ __('Client') }}</x-sort-th>
                    <th>{{ __('Zone') }}</th>
                    <th class="right">{{ __('Index précédent') }}</th>
                    <th></th>
                </tr>
                @foreach ($meters as $meter)
                    <tr>
                        <td>{{ $meter->number }}</td>
                        <td>{{ $meter->customer->name }}</td>
                        <td>{{ $meter->zone?->name ?? '—' }}</td>
                        <td class="right">{{ qty($meter->previous_index ?? $meter->initial_index) }} m³</td>
                        <td>
                            @if ($meter->period_index !== null)
                                <span class="badge ok">{{ __('Relevé : :v1 m³', ['v1' => qty($meter->period_index)]) }}</span>
                            @else
                                <form class="index-entry" method="POST" action="{{ route('readings.store') }}">
                                    @csrf
                                    <input type="hidden" name="meter_id" value="{{ $meter->id }}">
                                    <input type="hidden" name="period" value="{{ $period }}">
                                    <input type="hidden" name="read_at" value="{{ now()->format('Y-m-d') }}">
                                    <input type="number" name="current_index" step="0.001" min="0" required inputmode="decimal"
                                           value="{{ old('meter_id') == $meter->id ? old('current_index') : '' }}"
                                           placeholder="{{ __('Nouvel index') }}" aria-label="{{ __('Nouvel index du compteur :v1', ['v1' => $meter->number]) }}">
                                    <button type="submit" class="btn small">{{ __('Enregistrer') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table></div>
            {{ $meters->links() }}
        @endif
    </div>
@endsection
