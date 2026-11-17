@extends('layouts.app')
@section('title', __('Releveurs'))

@section('content')
    <div class="page-head">
        <h1>{{ __('Releveurs') }}</h1>
        <div class="actions">
            <x-info-button>{{ __('Un releveur peut seulement saisir les index et envoyer les factures impayées de sa zone, par WhatsApp ou SMS. Il ne voit rien d\'autre du compte.') }}</x-info-button>
            @if ($readers->count() < $max)
                <a class="btn" href="{{ route('readers.create') }}">{{ __('Ajouter un releveur') }}</a>
            @endif
        </div>
    </div>

    <p class="soft small">{{ __(':v1 releveur(s) sur :v2 possibles. Code du compte à leur donner : :v3', ['v1' => $readers->count(), 'v2' => $max, 'v3' => $code]) }}</p>

    <div class="card">
        @if ($readers->isEmpty())
            <p class="empty">{{ __('Aucun releveur pour le moment.') }}</p>
        @else
            <div class="table-wrap cards-wide"><table id="t-releveurs">
                <tr><th>{{ __('Nom') }}</th><th>{{ __('Login') }}</th><th>{{ __('Zones') }}</th><th></th></tr>
                @foreach ($readers as $reader)
                    <tr>
                        <td>{{ $reader->name }}</td>
                        <td>{{ $reader->email }}</td>
                        <td>{{ $reader->zonesLabel() }}</td>
                        <td>
                            <div class="actions">
                                <a class="btn ghost small" href="{{ route('readers.edit', $reader->id) }}">{{ __('Modifier') }}</a>
                                <form class="inline" method="POST" action="{{ route('readers.destroy', $reader->id) }}" data-confirm="{{ __('Supprimer ce releveur ? Ses relevés sont conservés.') }}" onsubmit="return confirm(this.dataset.confirm)">
                                    @csrf @method('DELETE')
                                    <button class="btn danger small">{{ __('Supprimer') }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </table></div>
        @endif
    </div>
@endsection
