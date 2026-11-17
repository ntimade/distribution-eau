@extends('layouts.app')
@section('title', __('Clients'))

@section('content')
    <div class="page-head">
        <h1>{{ __('Clients') }}</h1>
        <a class="btn" href="{{ route('customers.create') }}">{{ __('Ajouter un client') }}</a>
    </div>
    <div class="card">
        <form class="filters" method="GET">
            <div><label for="q">{{ __('Recherche') }}</label><input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Nom ou téléphone') }}"></div>
            <button class="btn ghost">{{ __('Filtrer') }}</button>
        </form>
        @if ($customers->isEmpty())
            <p class="empty">{{ __('Aucun client.') }}</p>
        @else
            <div class="table-wrap"><table id="t-clients">
                <tr><th><input type="checkbox" aria-label="{{ __('Tout cocher') }}" onclick="document.querySelectorAll('input[name=&quot;customer_ids[]&quot;]').forEach(function (box) { box.checked = event.target.checked; })"></th><x-sort-th :table="$customers" key="name">{{ __('Nom') }}</x-sort-th><x-sort-th :table="$customers" key="phone">{{ __('Téléphone') }}</x-sort-th><x-sort-th :table="$customers" key="price" kind="number">{{ __('Prix du m³') }}</x-sort-th><x-sort-th :table="$customers" key="meters" kind="number" class="right">{{ __('Compteurs') }}</x-sort-th><x-sort-th :table="$customers" key="unpaid" kind="number" class="right">{{ __('Impayés') }}</x-sort-th></tr>
                @foreach ($customers as $customer)
                    <tr>
                        <td><input type="checkbox" form="bulk-price" name="customer_ids[]" value="{{ $customer->id }}" aria-label="{{ __('Choisir :v1', ['v1' => $customer->name]) }}"></td>
                        <td><a href="{{ route('customers.show', $customer) }}">{{ $customer->name }}</a></td>
                        <td>{{ $customer->phone }}@if ($customer->phone && ! \App\Support\PhoneNumber::isInternational($customer->phone)) <span class="badge partial">{{ __('À corriger') }}</span>@endif</td>
                        <td>{{ $customer->price_per_m3 === null ? __('Général') : money($customer->price_per_m3) }}</td>
                        <td class="right">{{ $customer->meters_count }}</td>
                        <td class="right">@if ($customer->unpaid > 0)<span class="danger-text">{{ money($customer->unpaid) }}</span>@else<span class="badge ok">{{ __('À jour') }}</span>@endif</td>
                    </tr>
                @endforeach
            </table></div>
            {{ $customers->links() }}
        @endif
    </div>

    @if ($customers->isNotEmpty())
        <details class="card fold" @if ($errors->hasAny(['target', 'zone_id', 'price_per_m3', 'apply_period', 'customer_ids'])) open @endif>
            <summary>
                <div>
                    <h2>{{ __('Changer le prix du m³ de plusieurs clients') }}</h2>
                    <p class="soft small">{{ __('Cochez des clients dans la liste, ou choisissez une zone. Laissez le prix vide pour revenir au tarif général.') }}</p>
                </div>
            </summary>
            <form id="bulk-price" method="POST" action="{{ route('customers.bulk-price') }}" data-confirm="{{ __('Appliquer ce changement de prix ?') }}" onsubmit="return confirm(this.dataset.confirm)">
                @csrf
                <div class="form-grid">
                    <x-field name="target" :label="__('Appliquer à')" :required="true">
                        <select id="target" name="target" required>
                            <option value="selected" @selected(old('target') === 'selected')>{{ __('Clients cochés') }}</option>
                            <option value="zone" @selected(old('target') === 'zone')>{{ __('Clients d\'une zone') }}</option>
                            <option value="all" @selected(old('target') === 'all')>{{ __('Tous les clients') }}</option>
                        </select>
                    </x-field>
                    <x-field name="zone_id" :label="__('Zone')" :help="__('Seulement pour « Clients d\'une zone ».')">
                        <select id="zone_id" name="zone_id">
                            <option value="">{{ __('Choisir…') }}</option>
                            @foreach ($zones as $zone)
                                <option value="{{ $zone->id }}" @selected(old('zone_id') == $zone->id)>{{ $zone->name }}</option>
                            @endforeach
                        </select>
                    </x-field>
                    <x-field name="price_per_m3" :label="__('Nouveau prix du m³')" type="number" step="0.001" min="0.001" />
                    <x-field name="apply_period" :label="__('Recalculer aussi les factures du mois')" type="month"
                             :help="__('Facultatif, en cas d\'erreur de prix. Sinon, seuls les prochains relevés changent.')" />
                </div>
                @error('customer_ids')<p class="danger-text small">{{ $message }}</p>@enderror
                <button type="submit" class="btn">{{ __('Appliquer') }}</button>
            </form>
        </details>
    @endif
@endsection
