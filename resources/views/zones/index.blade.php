@extends('layouts.app')
@section('title', __('Zones'))

@section('content')
    <div class="page-head"><h1>{{ __('Zones géographiques') }}</h1></div>

    <div class="card">
        <h2>{{ __('Ajouter une zone') }}</h2>
        <form method="POST" action="{{ route('zones.store') }}">
            @csrf
            <div class="form-grid">
                <x-field name="name" :label="__('Nom')" :required="true" />
                <x-field name="description" :label="__('Description')" />
            </div>
            <button type="submit" class="btn">{{ __('Ajouter') }}</button>
        </form>
    </div>

    <div class="card">
        @if ($zones->isEmpty())
            <p class="empty">{{ __('Aucune zone. Elles sont facultatives et servent à regrouper les compteurs.') }}</p>
        @else
            <div class="table-wrap"><table id="t-zones">
                <tr><x-sort-th :table="$zones" key="name">{{ __('Nom') }}</x-sort-th><x-sort-th :table="$zones" key="description">{{ __('Description') }}</x-sort-th><x-sort-th :table="$zones" key="meters" kind="number" class="right">{{ __('Compteurs') }}</x-sort-th><th></th></tr>
                @foreach ($zones as $zone)
                    <tr>
                        <td>{{ $zone->name }}</td>
                        <td class="wrap">{{ $zone->description }}</td>
                        <td class="right">{{ $zone->meters_count }}</td>
                        <td>
                            <div class="actions">
                                <a class="btn ghost small" href="{{ route('zones.edit', $zone) }}">{{ __('Modifier') }}</a>
                                <form class="inline" method="POST" action="{{ route('zones.destroy', $zone) }}" data-confirm="{{ __('Supprimer cette zone ?') }}" onsubmit="return confirm(this.dataset.confirm)">
                                    @csrf @method('DELETE')
                                    <button class="btn danger small">{{ __('Supprimer') }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </table></div>
            {{ $zones->links() }}
        @endif
    </div>
@endsection
