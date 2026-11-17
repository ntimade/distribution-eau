<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Reçu') }} {{ $receipt->number() }} — {{ $organisation->name }}</title>
    <link rel="icon" type="image/png" href="{{ asset_v('images/favicon.png') }}">
    <link rel="stylesheet" href="{{ asset_v('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset_v('css/invoice.css') }}">
    <style>.receipt-meter { margin: 18px 0 6px; font-size: 1rem; }</style>
</head>
<body>
<div class="print-toolbar">
    <a class="btn ghost" href="{{ $backUrl }}">{{ __('Retour') }}</a>
    <x-lang-switch />
    <button type="button" class="btn" onclick="window.print()">{{ __('Imprimer') }}</button>
</div>

@include('receipts.sheet')
</body>
</html>
