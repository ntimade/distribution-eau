@extends('layouts.app')
@section('title', __('Impayés'))

@section('content')
    <div class="page-head"><h1>{{ __('Impayés') }}</h1></div>

    <div class="stats">
        <div class="stat alert"><span>{{ __('Total des impayés') }}</span><strong>{{ money($totalUnpaid) }}</strong></div>
        <div class="stat alert"><span>{{ __('Impayés :v1', ['v1' => of_period($period)]) }}</span><strong>{{ money($periodUnpaid) }}</strong></div>
    </div>

    <div class="card">
        <h2>{{ __('Par mois') }}</h2>
        @if ($months->isEmpty())
            <p class="empty">{{ __('Aucun relevé pour le moment.') }}</p>
        @else
            <div class="table-wrap"><table id="t-mois">
                <tr><x-sort-th :table="$months" key="period" kind="date">{{ __('Mois') }}</x-sort-th><x-sort-th :table="$months" key="readings" kind="number" class="right">{{ __('Relevés') }}</x-sort-th><x-sort-th :table="$months" key="billed" kind="number" class="right">{{ __('Facturé') }}</x-sort-th><x-sort-th :table="$months" key="paid" kind="number" class="right">{{ __('Payé') }}</x-sort-th><x-sort-th :table="$months" key="unpaid" kind="number" class="right">{{ __('Impayé') }}</x-sort-th></tr>
                @foreach ($months as $month)
                    <tr @class(['selected' => $month->period === $period])>
                        <td><a href="{{ request()->fullUrlWithQuery(['period' => $month->period, 'detail_page' => null]) }}#t-detail">{{ period_label($month->period) }}</a></td>
                        <td class="right">{{ $month->readings_count }}</td>
                        <td class="right">{{ money($month->billed) }}</td>
                        <td class="right">{{ money($month->paid) }}</td>
                        <td class="right @if ($month->unpaid > 0) danger-text @endif">{{ money($month->unpaid) }}</td>
                    </tr>
                @endforeach
            </table></div>
            {{ $months->links() }}
        @endif
    </div>

    <div class="card">
        <h2>{{ __('Détail :v1', ['v1' => of_period($period)]) }}</h2>
        @if ($readings->isEmpty())
            <p class="empty">{{ __('Aucun impayé pour ce mois.') }}</p>
        @else
            <div class="table-wrap cards-wide"><table id="t-detail">
                <tr><x-sort-th :table="$readings" key="client">{{ __('Client') }}</x-sort-th><x-sort-th :table="$readings" key="phone">{{ __('Téléphone') }}</x-sort-th><x-sort-th :table="$readings" key="meter">{{ __('Compteur') }}</x-sort-th><x-sort-th :table="$readings" key="amount" kind="number" class="right">{{ __('Montant') }}</x-sort-th><x-sort-th :table="$readings" key="paid" kind="number" class="right">{{ __('Payé') }}</x-sort-th><x-sort-th :table="$readings" key="rest" kind="number" class="right">{{ __('Reste') }}</x-sort-th><x-sort-th :table="$readings" key="due" kind="date">{{ __('Échéance') }}</x-sort-th><th></th></tr>
                @foreach ($readings as $reading)
                    <tr>
                        <td><a href="{{ route('customers.show', $reading->customer) }}">{{ $reading->customer->name }}</a></td>
                        <td>{{ $reading->customer->phone }}</td>
                        <td>{{ $reading->meter->number }}@if ($reading->is_opening) <span class="badge off">{{ __('Solde de départ') }}</span>@endif</td>
                        <td class="right">{{ money($reading->amount) }}</td>
                        <td class="right">{{ money($reading->paid_amount) }}</td>
                        <td class="right danger-text">{{ money($reading->balance()) }}</td>
                        <td>{{ $reading->due_date?->format('d/m/Y') ?? '—' }}@if ($reading->isOverdue()) <span class="badge ko">{{ __('En retard') }}</span>@endif</td>
                        <td><div class="actions">@if ($reading->isOverdue())@include('messages.notice-send', ['customer' => $reading->customer, 'meterId' => $reading->meter_id])@else @include('messages.reminder-send', ['reading' => $reading])@endif<a class="btn ghost small" href="{{ route('readings.show', $reading) }}">{{ __('Encaisser') }}</a></div>@if ($reading->reminder_opened_at)<span class="soft small cell-line">{{ __('Rappel ouvert le :v1', ['v1' => $reading->reminder_opened_at->format('d/m/Y')]) }}</span>@endif</td>
                    </tr>
                @endforeach
            </table></div>
            {{ $readings->links() }}
        @endif
    </div>
@endsection
