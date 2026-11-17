@extends('layouts.app')
@section('title', __('Compteur ').$meter->number)

@section('content')
    <div class="page-head">
        <h1>{{ __('Compteur :v1', ['v1' => $meter->number]) }}</h1>
        <div class="actions">
            @if ($meter->customer_id)
                <a class="btn" href="{{ route('receipts.create', ['customer' => $meter->customer_id, 'meter' => $meter->id]) }}"><x-icon name="wallet" /> {{ __('Encaisser plusieurs mois') }}</a>
            @endif
            @if ($meter->customer_id && $meter->is_active)
                <a class="btn" href="{{ route('readings.create', ['meter_id' => $meter->id]) }}">{{ __('Saisir un relevé') }}</a>
                <a class="btn ghost" href="{{ route('meters.replace', $meter) }}">{{ __('Remplacer ce compteur') }}</a>
            @endif
            <a class="btn ghost" href="{{ route('meters.edit', $meter) }}">{{ __('Modifier') }}</a>
            <form class="inline" method="POST" action="{{ route('meters.destroy', $meter) }}" data-confirm="{{ __('Supprimer ce compteur ?') }}" onsubmit="return confirm(this.dataset.confirm)">
                @csrf @method('DELETE')
                <button class="btn danger">{{ __('Supprimer') }}</button>
            </form>
        </div>
    </div>

    @error('replacement')<div class="flash error">{{ $message }}</div>@enderror

    @foreach (array_filter([$meter->replacement, $meter->replacedFrom]) as $replacement)
        <div class="flash info">
            @if ($replacement->old_meter_id === $meter->id)
                {{ __('Remplacé par le compteur') }} <a href="{{ route('meters.show', $replacement->new_meter_id) }}">{{ $replacement->newMeter->number }}</a>
            @else
                {{ __('Remplace le compteur') }} <a href="{{ route('meters.show', $replacement->old_meter_id) }}">{{ $replacement->oldMeter->number }}</a>
            @endif
            {{ __('le :v1. Index final de l\'ancien compteur : :v2 m³, soit :v3 m³ reportés sur la facture du nouveau.', ['v1' => $replacement->replaced_on->format('d/m/Y'), 'v2' => qty($replacement->old_final_index), 'v3' => qty($replacement->old_consumption)]) }}
            @if ($replacement->notes)<br>{{ $replacement->notes }}@endif
            <div class="actions" style="justify-content: flex-start; margin-top: 8px">
                <a class="btn ghost small" href="{{ route('meters.replacements.edit', $replacement) }}">{{ __('Corriger') }}</a>
                @unless ($replacement->newMeter->readings()->exists())
                    <form class="inline" method="POST" action="{{ route('meters.replacements.destroy', $replacement) }}" data-confirm="{{ __('Annuler ce remplacement ? Le nouveau compteur sera supprimé et l\'ancien réactivé.') }}" onsubmit="return confirm(this.dataset.confirm)">
                        @csrf @method('DELETE')
                        <button class="btn danger small">{{ __('Annuler le remplacement') }}</button>
                    </form>
                @endunless
            </div>
        </div>
    @endforeach

    <div class="card">
        <dl class="detail">
            <div><dt>{{ __('Client') }}</dt><dd>
                @if ($meter->customer)<a href="{{ route('customers.show', $meter->customer) }}">{{ $meter->customer->name }}</a>@else {{ __('Non rattaché') }} @endif
            </dd></div>
            <div><dt>{{ __('Paiements') }}</dt><dd>
                @if ($meter->paymentStatus() === 'late')
                    <span class="badge ko">{{ __('Non en règle') }}</span>
                    <span class="danger-text small">{{ __(':v1 sur :v2 mois', ['v1' => money($meter->unpaid), 'v2' => $meter->unpaid_months]) }}</span>
                @elseif ($meter->paymentStatus() === 'ok')
                    <span class="badge ok">{{ __('En règle') }}</span>
                @else
                    <span class="badge off">{{ __('Aucun client') }}</span>
                @endif
            </dd></div>
            <div><dt>{{ __('Zone') }}</dt><dd>{{ $meter->zone?->name ?? '—' }}</dd></div>
            <div><dt>{{ __('Index de départ') }}</dt><dd>{{ qty($meter->initial_index) }} m³</dd></div>
            <div><dt>{{ __('Date de pose') }}</dt><dd>{{ $meter->installed_at?->format('d/m/Y') ?? '—' }}</dd></div>
            <div><dt>{{ __('Position') }}</dt><dd>{{ $meter->latitude !== null ? (float) $meter->latitude.', '.(float) $meter->longitude : '—' }}</dd></div>
            <div><dt>{{ __('État') }}</dt><dd>{{ $meter->is_active ? __('Actif') : __('Désactivé') }}</dd></div>
        </dl>
    </div>

    <div class="card">
        <h2>{{ __('Historique des relevés') }}</h2>
        @if ($readings->isEmpty())
            <p class="empty">{{ __('Aucun relevé.') }}</p>
        @else
            <div class="table-wrap"><table id="t-releves">
                <tr><x-sort-th :table="$readings" key="period" kind="date">{{ __('Mois') }}</x-sort-th><x-sort-th :table="$readings" key="client">{{ __('Client') }}</x-sort-th><x-sort-th :table="$readings" key="previous" kind="number" class="right">{{ __('Index précédent') }}</x-sort-th><x-sort-th :table="$readings" key="current" kind="number" class="right">{{ __('Nouvel index') }}</x-sort-th><x-sort-th :table="$readings" key="consumption" kind="number" class="right">{{ __('Conso.') }}</x-sort-th><x-sort-th :table="$readings" key="amount" kind="number" class="right">{{ __('Montant') }}</x-sort-th><x-sort-th :table="$readings" key="due" kind="date">{{ __('Échéance') }}</x-sort-th><th></th></tr>
                @foreach ($readings as $reading)
                    <tr>
                        <td><a href="{{ route('readings.show', $reading) }}">{{ $reading->label() }}</a></td>
                        <td>{{ $reading->customer->name }}</td>
                        <td class="right">{{ qty($reading->previous_index) }}</td>
                        <td class="right">{{ qty($reading->current_index) }}</td>
                        <td class="right">{{ qty($reading->consumption) }} m³</td>
                        <td class="right">{{ money($reading->amount) }}</td>
                        <td>{{ $reading->due_date?->format('d/m/Y') ?? '—' }}</td>
                        <td>@include('readings.status', ['reading' => $reading])</td>
                    </tr>
                @endforeach
            </table></div>
            {{ $readings->links() }}
        @endif
    </div>
@endsection
