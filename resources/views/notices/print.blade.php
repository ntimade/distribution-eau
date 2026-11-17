<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — {{ auth()->user()->organisation->name }}</title>
    <link rel="icon" type="image/png" href="{{ asset_v('images/favicon.png') }}">
    <link rel="stylesheet" href="{{ asset_v('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset_v('css/invoice.css') }}">
</head>
<body>
@php
    $organisation = auth()->user()->organisation;
@endphp

<div class="print-toolbar">
    <a class="btn ghost" href="{{ $backUrl }}">{{ __('Retour') }}</a>
    <span class="soft small">{{ __(':v1 avis', ['v1' => $notices->count()]) }}</span>
    <x-lang-switch />
    <button type="button" class="btn" onclick="window.print()">{{ __('Imprimer') }}</button>
</div>

@foreach ($notices as $notice)
    @php
        $readings = $notice->overdueReadings()->get();
    @endphp
    <article class="invoice">
        <header class="invoice-head">
            <div class="invoice-org">
                @if ($organisation->logo_path)
                    <img class="invoice-logo" src="{{ route('settings.logo', ['v' => $organisation->updated_at?->timestamp]) }}" alt="{{ __('Logo de :v1', ['v1' => $organisation->name]) }}">
                @endif
                <strong>{{ $organisation->name }}</strong>
                @if ($organisation->address)<span>{{ $organisation->address }}</span>@endif
                @if ($organisation->phone)<span>{{ __('Tél. : :v1', ['v1' => $organisation->phone]) }}</span>@endif
            </div>
            <div class="invoice-title">
                <h1>{{ __('Avis de coupure') }}</h1>
                <span class="invoice-number">{{ $notice->number() }}</span>
                <span>{{ __('Émis le :v1', ['v1' => $notice->issued_at->format('d/m/Y')]) }}</span>
            </div>
        </header>

        <section class="invoice-parties">
            <div>
                <h2>{{ __('Client') }}</h2>
                <strong>{{ $notice->customer->name }}</strong>
                @if ($notice->customer->address)<span>{{ $notice->customer->address }}</span>@endif
                @if ($notice->customer->phone)<span>{{ __('Tél. : :v1', ['v1' => $notice->customer->phone]) }}</span>@endif
            </div>
            <div>
                <h2>{{ __('Compteur') }}</h2>
                <strong>{{ $notice->meter->number }}</strong>
            </div>
        </section>

        <p>{{ __('Malgré l\'échéance dépassée, les factures suivantes restent impayées :') }}</p>

        <table class="invoice-lines notice-lines">
            <thead>
                <tr><th>{{ __('Facture') }}</th><th>{{ __('Période') }}</th><th>{{ __('Échéance') }}</th><th>{{ __('Reste à payer') }}</th></tr>
            </thead>
            <tbody>
                @foreach ($readings as $reading)
                    <tr>
                        <td>{{ $reading->invoiceNumber() }}</td>
                        <td>{{ $reading->label() }}</td>
                        <td>{{ $reading->due_date->format('d/m/Y') }}</td>
                        <td>{{ money($reading->balance()) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="invoice-totals">
            <tr class="grand-total"><th>{{ __('Total à régler') }}</th><td>{{ money($readings->sum(fn ($reading) => $reading->balance())) }}</td></tr>
        </table>

        <p class="notice-warning">
            {{ __('Sans règlement de ce montant avant le') }} <strong>{{ $notice->deadline->format('d/m/Y') }}</strong>{{ __(', l\'alimentation en eau de ce compteur sera coupée.') }}
        </p>
        <p class="invoice-note">
            {{ __('Montant à la date d\'impression du :v1. Si vous avez déjà réglé, merci de ne pas tenir compte de cet avis', ['v1' => now()->format('d/m/Y')]) }}
            @if ($organisation->phone) {{ __('ou de nous contacter au :v1', ['v1' => $organisation->phone]) }}@endif.
        </p>
    </article>
@endforeach
</body>
</html>
