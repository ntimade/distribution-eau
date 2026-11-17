{{-- Le reçu d'un encaissement de plusieurs mois : chaque compteur reste séparé, avec ses mois, son sous-total et son reste dû. --}}
<article class="invoice">
    <header class="invoice-head">
        <div class="invoice-org">
            @if ($organisation->logo_path && $logoSrc)
                <img class="invoice-logo" src="{{ $logoSrc }}" alt="{{ __('Logo de :v1', ['v1' => $organisation->name]) }}">
            @endif
            <strong>{{ $organisation->name }}</strong>
            @if ($organisation->address)<span>{{ $organisation->address }}</span>@endif
            @if ($organisation->phone)<span>{{ __('Tél. : :v1', ['v1' => $organisation->phone]) }}</span>@endif
        </div>
        <div class="invoice-title">
            <h1>{{ __('Reçu d\'encaissement') }}</h1>
            <span class="invoice-number">{{ $receipt->number() }}</span>
            <span>{{ __('Payé le :v1', ['v1' => $receipt->paid_at->format('d/m/Y')]) }}</span>
            <span>{{ __('Imprimé le :v1', ['v1' => now()->format('d/m/Y')]) }}</span>
        </div>
    </header>

    <section class="invoice-parties">
        <div>
            <h2>{{ __('Client') }}</h2>
            <strong>{{ $receipt->customer->name }}</strong>
            @if ($receipt->customer->address)<span>{{ $receipt->customer->address }}</span>@endif
            @if ($receipt->customer->phone)<span>{{ __('Tél. : :v1', ['v1' => $receipt->customer->phone]) }}</span>@endif
        </div>
        <div>
            <h2>{{ __('Paiement') }}</h2>
            <strong>{{ money($receipt->total) }}</strong>
            @if ($receipt->method)<span>{{ __('Mode : :v1', ['v1' => $receipt->method]) }}</span>@endif
            @if ($receipt->reference)<span>{{ __('Référence : :v1', ['v1' => $receipt->reference]) }}</span>@endif
            @if ($receipt->user)<span>{{ __('Encaissé par : :v1', ['v1' => $receipt->user->name]) }}</span>@endif
        </div>
    </section>

    @foreach ($groups as $group)
        <h3 class="receipt-meter">{{ __('Compteur :v1', ['v1' => $group->meter->number]) }}</h3>
        <table class="invoice-lines">
            <thead>
                <tr><th>{{ __('Mois') }}</th><th>{{ __('Facture') }}</th><th>{{ __('Montant de la facture') }}</th><th>{{ __('Payé par ce reçu') }}</th><th>{{ __('Reste sur la facture') }}</th></tr>
            </thead>
            <tbody>
                @foreach ($group->payments as $payment)
                    <tr>
                        <td>{{ $payment->reading->label() }}</td>
                        <td>{{ $payment->reading->invoiceNumber() }}</td>
                        <td>{{ money($payment->reading->amount) }}</td>
                        <td>{{ money($payment->amount) }}</td>
                        <td>{{ $payment->reading->balance() > 0 ? money($payment->reading->balance()) : __('Payée') }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="3">{{ __('Sous-total du compteur :v1', ['v1' => $group->meter->number]) }}</td>
                    <td>{{ money($group->subtotal) }}</td>
                    <td>{{ $group->remaining > 0 ? __('Reste dû : :v1', ['v1' => money($group->remaining)]) : __('À jour') }}</td>
                </tr>
            </tbody>
        </table>
    @endforeach

    <table class="invoice-totals">
        <tr class="grand-total"><th>{{ __('Total encaissé') }}</th><td>{{ money($receipt->total) }}</td></tr>
        <tr><th>{{ __('Reste à payer sur ces compteurs') }}</th><td>{{ money($groups->sum('remaining')) }}</td></tr>
    </table>

    <p class="invoice-note">{{ __('Ce reçu récapitule les mois réglés en une seule fois. Chaque facture garde le détail de ses paiements.') }}</p>
</article>
