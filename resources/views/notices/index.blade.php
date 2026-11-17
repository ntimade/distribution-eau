@extends('layouts.app')
@section('title', __('Avis de coupure'))

@section('content')
    <div class="page-head">
        <h1>{{ __('Avis de coupure') }}</h1>
        @if ($rows->isNotEmpty())
            <div class="actions">
                @if ($withNotice > 0 && $withoutNotice === 0)
                    <a class="btn" href="{{ route('notices.print') }}">{{ __('Imprimer tous les avis') }}</a>
                @else
                    <form class="inline" method="POST" action="{{ route('notices.store-all') }}">
                        @csrf
                        <button class="btn">{{ __('Générer et imprimer tous les avis') }}</button>
                    </form>
                @endif
            </div>
        @endif
    </div>

    <div class="stats">
        <div class="stat alert"><span>{{ __('Compteurs en retard') }}</span><strong>{{ $rows->count() }}</strong></div>
        <div class="stat"><span>{{ __('Sans avis') }}</span><strong>{{ $withoutNotice }}</strong></div>
        <div class="stat alert"><span>{{ __('Date limite dépassée') }}</span><strong>{{ $toCut }}</strong></div>
    </div>

    <div class="card">
        @if ($rows->isEmpty())
            <p class="empty">{{ __('Aucune facture en retard. Les avis apparaissent ici une fois l\'échéance dépassée.') }}</p>
        @else
            <div class="table-wrap cards-wide"><table id="t-avis">
                <tr><x-sort-th :table="$rows" key="client">{{ __('Client') }}</x-sort-th><x-sort-th :table="$rows" key="phone">{{ __('Téléphone') }}</x-sort-th><x-sort-th :table="$rows" key="meter">{{ __('Compteur') }}</x-sort-th><x-sort-th :table="$rows" key="invoices" kind="number" class="right">{{ __('Factures') }}</x-sort-th><x-sort-th :table="$rows" key="total" kind="number" class="right">{{ __('Montant dû') }}</x-sort-th><x-sort-th :table="$rows" key="due" kind="date">{{ __('Échéance la plus ancienne') }}</x-sort-th><x-sort-th :table="$rows" key="status">{{ __('État') }}</x-sort-th><th></th></tr>
                @foreach ($rows as $row)
                    <tr>
                        <td><a href="{{ route('customers.show', $row->customer) }}">{{ $row->customer->name }}</a></td>
                        <td>{{ $row->customer->phone }}</td>
                        <td><a href="{{ route('meters.show', $row->meter) }}">{{ $row->meter->number }}</a></td>
                        <td class="right">{{ $row->invoices }}</td>
                        <td class="right danger-text">{{ money($row->total) }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($row->oldest_due)->format('d/m/Y') }}</td>
                        <td>
                            @if ($row->status === 'cut')
                                <span class="badge ko">{{ __('À couper depuis le :v1', ['v1' => $row->notice->deadline->format('d/m/Y')]) }}</span>
                            @elseif ($row->status === 'sent')
                                <span class="badge partial">{{ __('Avis jusqu\'au :v1', ['v1' => $row->notice->deadline->format('d/m/Y')]) }}</span>
                            @else
                                <span class="badge off">{{ __('Sans avis') }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="actions">
                                @include('messages.notice-send', ['customer' => $row->customer, 'meterId' => $row->meter->id, 'sent' => (bool) $row->notice?->message_opened_at])
                                @if ($row->notice)
                                    <a class="btn ghost small" href="{{ route('notices.show', $row->notice) }}">{{ __('Imprimer l\'avis') }}</a>
                                @else
                                    <form class="inline" method="POST" action="{{ route('notices.store') }}">
                                        @csrf
                                        <input type="hidden" name="meter_id" value="{{ $row->meter->id }}">
                                        <input type="hidden" name="customer_id" value="{{ $row->customer->id }}">
                                        <button class="btn ghost small">{{ __('Générer l\'avis') }}</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </table></div>
            {{ $rows->links() }}
        @endif
    </div>
@endsection
