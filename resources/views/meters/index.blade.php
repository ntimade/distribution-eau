@extends('layouts.app')
@section('title', __('Compteurs'))

@section('content')
    <div class="page-head">
        <h1>{{ __('Compteurs') }}</h1>
        <a class="btn" href="{{ route('meters.create') }}">{{ __('Ajouter un compteur') }}</a>
    </div>
    <div class="card">
        <form class="filters" method="GET">
            <div><label for="q">{{ __('Numéro') }}</label><input id="q" type="search" name="q" value="{{ request('q') }}"></div>
            <div>
                <label for="zone_id">{{ __('Zone') }}</label>
                <select id="zone_id" name="zone_id">
                    <option value="">{{ __('Toutes') }}</option>
                    @foreach ($zones as $zone)
                        <option value="{{ $zone->id }}" @selected(request('zone_id') == $zone->id)>{{ $zone->name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn ghost">{{ __('Filtrer') }}</button>
        </form>
        @if ($meters->isEmpty())
            <p class="empty">{{ __('Aucun compteur.') }}</p>
        @else
            <div class="table-wrap"><table id="t-compteurs">
                <tr><x-sort-th :table="$meters" key="number">{{ __('Numéro') }}</x-sort-th><x-sort-th :table="$meters" key="client">{{ __('Client') }}</x-sort-th><x-sort-th :table="$meters" key="zone">{{ __('Zone') }}</x-sort-th><x-sort-th :table="$meters" key="last" kind="date">{{ __('Dernier relevé') }}</x-sort-th><x-sort-th :table="$meters" key="map">{{ __('Carte') }}</x-sort-th><x-sort-th :table="$meters" key="active">{{ __('État') }}</x-sort-th></tr>
                @foreach ($meters as $meter)
                    <tr>
                        <td><a href="{{ route('meters.show', $meter) }}">{{ $meter->number }}</a></td>
                        <td>{{ $meter->customer?->name ?? __('— non rattaché —') }}</td>
                        <td>{{ $meter->zone?->name ?? '—' }}</td>
                        <td>{{ $meter->readings_max_period ? period_label($meter->readings_max_period) : '—' }}</td>
                        <td>{{ $meter->latitude !== null ? __('Oui') : '—' }}</td>
                        <td><span class="badge {{ $meter->is_active ? 'ok' : 'off' }}">{{ $meter->is_active ? __('Actif') : __('Désactivé') }}</span></td>
                    </tr>
                @endforeach
            </table></div>
            {{ $meters->links() }}
        @endif
    </div>
@endsection
