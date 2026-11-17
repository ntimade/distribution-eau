@php
    $previousDebt = (float) ($arrears[$reading->meter_id.'-'.$reading->customer_id] ?? 0);
    $waterAmount = (float) $reading->amount - (float) $reading->maintenance_fee;
@endphp
<article class="invoice">
    <header class="invoice-head">
        <div class="invoice-org">
            @if ($organisation->logo_path)
                <img class="invoice-logo" src="{{ $logoSrc }}" alt="{{ __('Logo de :v1', ['v1' => $organisation->name]) }}">
            @endif
            <strong>{{ $organisation->name }}</strong>
            @if ($organisation->address)<span>{{ $organisation->address }}</span>@endif
            @if ($organisation->phone)<span>{{ __('Tél. : :v1', ['v1' => $organisation->phone]) }}</span>@endif
        </div>
        <div class="invoice-title">
            <h1>{{ __('Facture') }}</h1>
            <span class="invoice-number">{{ $reading->invoiceNumber() }}</span>
            <span>{{ __('Période : :v1', ['v1' => $reading->label()]) }}</span>
            <span>{{ __('Imprimée le :v1', ['v1' => now()->format('d/m/Y')]) }}</span>
            @if ($reading->due_date)<strong class="invoice-due">{{ __('À payer avant le :v1', ['v1' => $reading->due_date->format('d/m/Y')]) }}</strong>@endif
        </div>
    </header>

    <section class="invoice-parties">
        <div>
            <h2>{{ __('Client') }}</h2>
            <strong>{{ $reading->customer->name }}</strong>
            @if ($reading->customer->address)<span>{{ $reading->customer->address }}</span>@endif
            @if ($reading->customer->phone)<span>{{ __('Tél. : :v1', ['v1' => $reading->customer->phone]) }}</span>@endif
        </div>
        <div>
            <h2>{{ __('Compteur') }}</h2>
            <strong>{{ $reading->meter->number }}</strong>
            @if ($reading->meter->zone)<span>{{ __('Zone : :v1', ['v1' => $reading->meter->zone->name]) }}</span>@endif
            @unless ($reading->is_opening)<span>{{ __('Relevé le :v1', ['v1' => $reading->read_at->format('d/m/Y')]) }}</span>@endunless
        </div>
    </section>

    @if ($reading->replacement)
        <table class="invoice-lines">
            <thead>
                <tr><th>{{ __('Compteur') }}</th><th>{{ __('Index précédent') }}</th><th>{{ __('Nouvel index') }}</th><th>{{ __('Consommation') }}</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ __(':v1 (remplacé le :v2)', ['v1' => $reading->replacement->oldMeter->number, 'v2' => $reading->replacement->replaced_on->format('d/m/Y')]) }}</td>
                    <td>{{ qty($reading->replacement->old_previous_index) }} m³</td>
                    <td>{{ qty($reading->replacement->old_final_index) }} m³</td>
                    <td>{{ qty($reading->carried_consumption) }} m³</td>
                </tr>
                <tr>
                    <td>{{ __(':v1 (nouveau)', ['v1' => $reading->meter->number]) }}</td>
                    <td>{{ qty($reading->previous_index) }} m³</td>
                    <td>{{ qty($reading->current_index) }} m³</td>
                    <td>{{ qty((float) $reading->consumption - (float) $reading->carried_consumption) }} m³</td>
                </tr>
                <tr>
                    <td colspan="3">{{ __('Total : :v1 m³ × :v2', ['v1' => qty($reading->consumption), 'v2' => money($reading->unit_price)]) }}</td>
                    <td>{{ money($waterAmount) }}</td>
                </tr>
            </tbody>
        </table>
    @elseif (! $reading->is_opening)
        <table class="invoice-lines">
            <thead>
                <tr><th>{{ __('Index précédent') }}</th><th>{{ __('Nouvel index') }}</th><th>{{ __('Consommation') }}</th><th>{{ __('Prix du m³') }}</th><th>{{ __('Montant') }}</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ qty($reading->previous_index) }} m³</td>
                    <td>{{ qty($reading->current_index) }} m³</td>
                    <td>{{ qty($reading->consumption) }} m³</td>
                    <td>{{ money($reading->unit_price) }}</td>
                    <td>{{ money($waterAmount) }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <table class="invoice-totals">
        @if ($reading->is_opening)
            <tr><th>{{ __('Solde repris de l\'ancien système') }}</th><td>{{ money($reading->amount) }}</td></tr>
        @else
            <tr><th>{{ __('Consommation d\'eau') }}</th><td>{{ money($waterAmount) }}</td></tr>
            @if ((float) $reading->maintenance_fee > 0)
                <tr><th>{{ __('Frais d\'entretien') }}</th><td>{{ money($reading->maintenance_fee) }}</td></tr>
            @endif
            <tr class="subtotal"><th>{{ __('Total de la facture') }}</th><td>{{ money($reading->amount) }}</td></tr>
        @endif
        @if ((float) $reading->paid_amount > 0)
            <tr><th>{{ __('Déjà payé') }}</th><td>− {{ money($reading->paid_amount) }}</td></tr>
        @endif
        @if ($previousDebt > 0)
            <tr><th>{{ __('Impayés des mois précédents') }}</th><td>{{ money($previousDebt) }}</td></tr>
        @endif
        <tr class="grand-total"><th>{{ __('Total à payer') }}</th><td>{{ money($reading->balance() + $previousDebt) }}</td></tr>
    </table>

    @if ($reading->payments->isNotEmpty())
        {{-- Le détail complet des paiements de cette facture, y compris ceux faits par un encaissement de plusieurs mois. --}}
        <h3 class="invoice-payments-title">{{ __('Détail des paiements') }}</h3>
        <table class="invoice-lines invoice-payments">
            <thead>
                <tr><th>{{ __('Date') }}</th><th>{{ __('Mode') }}</th><th>{{ __('Référence') }}</th><th>{{ __('Reçu') }}</th><th>{{ __('Montant') }}</th></tr>
            </thead>
            <tbody>
                @foreach ($reading->payments->sortBy(fn ($payment) => [$payment->paid_at->format('Y-m-d'), $payment->id]) as $payment)
                    <tr>
                        <td>{{ $payment->paid_at->format('d/m/Y') }}</td>
                        <td>{{ $payment->method ?: '—' }}</td>
                        <td>{{ $payment->reference ?: '—' }}</td>
                        <td>{{ $payment->receipt?->number() ?? '—' }}</td>
                        <td>{{ money($payment->amount) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="4">{{ __('Total payé sur cette facture') }}</td>
                    <td>{{ money($reading->paid_amount) }}</td>
                </tr>
                <tr>
                    <td colspan="4">{{ __('Reste à payer sur cette facture') }}</td>
                    <td>{{ $reading->balance() > 0 ? money($reading->balance()) : __('Payée') }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <p class="invoice-note">{{ __('En cas de non-paiement à l\'échéance, l\'alimentation en eau peut être coupée après un avis de coupure.') }}</p>
    @if ($reading->notes)<p class="invoice-note">{{ __('Observation : :v1', ['v1' => $reading->notes]) }}</p>@endif
</article>
