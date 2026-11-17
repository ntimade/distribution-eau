@extends('layouts.app')
@section('title', __('Paramètres'))

@section('content')
    <div class="page-head"><h1>{{ __('Paramètres') }}</h1></div>

    <nav class="subnav" aria-label="{{ __('Sections des paramètres') }}">
        <a href="#section-organisation">{{ __('Tarif') }}</a>
        <a href="#section-echeances">{{ __('Échéances') }}</a>
        <a href="#section-echeance-mois">{{ __('Échéance d\'un mois') }}</a>
        <a href="#section-logo">{{ __('Logo') }}</a>
        <a href="#section-entretien">{{ __('Entretien') }}</a>
        <a href="#section-mot-de-passe">{{ __('Mot de passe') }}</a>
        <a href="#section-questions">{{ __('Questions de sécurité') }}</a>
    </nav>

    <div class="card narrow" id="section-organisation">
        <h2>{{ __('Organisation et facturation') }}</h2>
        <form method="POST" action="{{ route('settings.update') }}">
            @csrf @method('PUT')
            <x-field name="name" :label="__('Nom de l\'organisation')" :value="$organisation->name" :required="true" />
            <div class="form-grid">
                <x-field name="phone" :label="__('Téléphone')" type="tel" :value="$organisation->phone" />
                <x-field name="address" :label="__('Adresse')" :value="$organisation->address" />
            </div>
            <x-field name="phone_country" :label="__('Pays')" :required="false" :help="__('Sert à mettre les numéros de vos clients au format international.')">
                    <select id="phone_country" name="phone_country" @required(false)>
                        <option value="">{{ __('Choisir…') }}</option>
                        @foreach (config('phone_countries') as $iso => [$label, $code])
                            <option value="{{ $iso }}" @selected(old('phone_country', $organisation->phone_country) === $iso)>{{ __($label) }} (+{{ $code }})</option>
                        @endforeach
                    </select>
                </x-field>
            <div class="form-grid">
                <x-field name="currency" :label="__('Devise')" :required="true"
                         :help="$currencyLocked ? __('Non modifiable : des relevés sont déjà facturés dans cette devise.') : null">
                    @if ($currencyLocked)
                        <input type="hidden" name="currency" value="{{ $organisation->currency }}">
                    @endif
                    <select id="currency" @unless ($currencyLocked) name="currency" @endunless @disabled($currencyLocked) required>
                        @foreach ($currencies as $code => [$label])
                            <option value="{{ $code }}" @selected(old('currency', $organisation->currency) === $code)>{{ $code }} — {{ __($label) }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="price_per_m3" :label="__('Prix du mètre cube')" type="number" step="0.001" min="0.001" :required="true"
                         :value="$organisation->price_per_m3 === null ? null : (float) $organisation->price_per_m3"
                         :help="__('S\'applique aux prochains relevés. Les relevés déjà saisis gardent leur prix.')" />
            </div>
            <button type="submit" class="btn">{{ __('Enregistrer') }}</button>
        </form>
    </div>

    <div class="card narrow" id="section-echeances">
        <h2>{{ __('Échéances et coupure') }}</h2>
        <form method="POST" action="{{ route('settings.billing-delays') }}">
            @csrf @method('PUT')
            <div class="form-grid">
                <x-field name="payment_due_days" :label="__('Délai de paiement (jours)')" type="number" min="1" max="365" step="1" :required="true"
                         :value="$organisation->payment_due_days" :help="__('Compté à partir de la date du relevé. Les factures existantes gardent leur échéance.')" />
                <x-field name="cutoff_grace_days" :label="__('Délai avant coupure (jours)')" type="number" min="1" max="365" step="1" :required="true"
                         :value="$organisation->cutoff_grace_days" :help="__('Compté à partir de la date de l\'avis de coupure.')" />
            </div>
            <button type="submit" class="btn">{{ __('Enregistrer') }}</button>
        </form>
    </div>

    <div class="card narrow" id="section-echeance-mois">
        <h2>{{ __('Échéance d\'un mois') }}</h2>
        <p class="soft small">{{ __('Fixe la même date pour toutes les factures du mois, y compris celles déjà saisies. Une date corrigée sur une seule facture est remplacée. Sans date ici, le délai de paiement ci-dessus s\'applique.') }}</p>
        <form method="POST" action="{{ route('settings.monthly-due') }}">
            @csrf @method('PUT')
            <div class="form-grid">
                <x-field name="monthly_period" :label="__('Mois')" type="month" :value="$currentPeriod" :required="true" />
                <x-field name="monthly_due_date" :label="__('Date d\'échéance')" type="date" :required="true" />
            </div>
            <button type="submit" class="btn">{{ __('Enregistrer') }}</button>
        </form>

        @if ($monthlyDueDates->isNotEmpty())
            <div class="table-wrap" style="margin-top: 16px"><table id="t-echeances">
                <tr><x-sort-th :table="$monthlyDueDates" key="month" kind="date">{{ __('Mois') }}</x-sort-th><x-sort-th :table="$monthlyDueDates" key="due" kind="date">{{ __('Échéance') }}</x-sort-th><x-sort-th :table="$monthlyDueDates" key="invoices" kind="number" class="right">{{ __('Factures') }}</x-sort-th><th></th></tr>
                @foreach ($monthlyDueDates as $monthly)
                    <tr>
                        <td>{{ period_label($monthly->period) }}</td>
                        <td>{{ $monthly->due_date->format('d/m/Y') }}</td>
                        <td class="right">{{ $invoiceCounts[$monthly->period] ?? 0 }}</td>
                        <td>
                            <div class="actions">
                                <form class="inline" method="POST" action="{{ route('settings.monthly-due.destroy', $monthly) }}" data-confirm="{{ __('Revenir au délai de paiement habituel pour ce mois ?') }}" onsubmit="return confirm(this.dataset.confirm)">
                                    @csrf @method('DELETE')
                                    <button class="btn danger small">{{ __('Retirer') }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </table></div>
            {{ $monthlyDueDates->links() }}
        @endif
    </div>

    <div class="card narrow" id="section-logo">
        <h2>{{ __('Logo') }}</h2>
        <p class="soft small">{{ __('Il apparaît sur vos factures et dans le menu. Formats PNG, JPG ou WEBP, 2 Mo au maximum.') }}</p>
        @if ($organisation->logo_path)
            <img class="logo-preview" src="{{ route('settings.logo', ['v' => $organisation->updated_at?->timestamp]) }}" alt="{{ __('Logo actuel') }}">
        @endif
        <form method="POST" action="{{ route('settings.logo.update') }}" enctype="multipart/form-data">
            @csrf
            <x-field name="logo" :label="$organisation->logo_path ? __('Remplacer le logo') : __('Choisir un logo')">
                <input id="logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp" required>
            </x-field>
            <button type="submit" class="btn">{{ __('Enregistrer le logo') }}</button>
        </form>
        @if ($organisation->logo_path)
            <form class="inline" method="POST" action="{{ route('settings.logo.destroy') }}" data-confirm="{{ __('Retirer le logo ?') }}" onsubmit="return confirm(this.dataset.confirm)">
                @csrf @method('DELETE')
                <p style="margin: 10px 0 0"><button class="btn danger small">{{ __('Retirer le logo') }}</button></p>
            </form>
        @endif
    </div>

    <div class="card narrow" id="section-entretien">
        <h2>{{ __('Frais d\'entretien') }}</h2>
        <p class="soft small">{{ __('Ils s\'ajoutent une seule fois à chaque facture du mois choisi. Les autres mois ne sont pas concernés.') }}</p>
        <form method="POST" action="{{ route('settings.maintenance') }}">
            @csrf @method('PUT')
            <div class="field">
                <label class="check">
                    <input type="checkbox" name="maintenance_enabled" value="1"
                           @checked(old('maintenance_period') ? old('maintenance_enabled') : $currentFee)> {{ __('Appliquer les frais d\'entretien') }}
                </label>
            </div>
            <div class="form-grid">
                <x-field name="maintenance_amount" :label="__('Montant par facture')" type="number" step="any" min="0"
                         :value="$currentFee ? (float) $currentFee->amount : null" />
                <x-field name="maintenance_period" :label="__('Mois')" type="month" :value="$currentPeriod" :required="true" />
            </div>
            <button type="submit" class="btn">{{ __('Enregistrer') }}</button>
        </form>

        @if ($maintenanceFees->isNotEmpty())
            <div class="table-wrap" style="margin-top: 16px"><table id="t-frais">
                <tr><x-sort-th :table="$maintenanceFees" key="month" kind="date">{{ __('Mois') }}</x-sort-th><x-sort-th :table="$maintenanceFees" key="amount" kind="number" class="right">{{ __('Montant') }}</x-sort-th><x-sort-th :table="$maintenanceFees" key="invoices" kind="number" class="right">{{ __('Factures') }}</x-sort-th><th></th></tr>
                @foreach ($maintenanceFees as $fee)
                    <tr>
                        <td>{{ period_label($fee->period) }}</td>
                        <td class="right">{{ money($fee->amount) }}</td>
                        <td class="right">{{ $invoiceCounts[$fee->period] ?? 0 }}</td>
                        <td>
                            <div class="actions">
                                <form class="inline" method="POST" action="{{ route('settings.maintenance.destroy', $fee) }}" data-confirm="{{ __('Retirer ces frais de toutes les factures du mois ?') }}" onsubmit="return confirm(this.dataset.confirm)">
                                    @csrf @method('DELETE')
                                    <button class="btn danger small">{{ __('Retirer') }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </table></div>
            {{ $maintenanceFees->links() }}
        @endif
    </div>

    <div class="card narrow" id="section-mot-de-passe">
        <h2>{{ __('Changer mon mot de passe') }}</h2>
        @if (auth()->user()->must_change_password)
            <div class="flash error">{{ __('Choisissez un nouveau mot de passe pour continuer.') }}</div>
        @endif
        <form method="POST" action="{{ route('settings.password') }}">
            @csrf @method('PUT')
            <x-field name="current_password" :label="__('Mot de passe actuel')" type="password" :required="true" errorBag="password" autocomplete="current-password" />
            <div class="form-grid">
                <x-field name="password" :label="__('Nouveau mot de passe')" type="password" :required="true" minlength="8" errorBag="password" autocomplete="new-password" />
                <x-field name="password_confirmation" :label="__('Confirmer')" type="password" :required="true" autocomplete="new-password" />
            </div>
            <button type="submit" class="btn">{{ __('Changer le mot de passe') }}</button>
        </form>
    </div>

    <div class="card narrow" id="section-questions">
        <h2>{{ __('Questions de sécurité') }}</h2>
        <p class="soft small">{{ __('Elles permettent de récupérer votre mot de passe en cas d\'oubli. Choisissez des réponses que vous seul connaissez.') }}</p>
        @if (! $securityQuestions)
            <div class="flash info">{{ __('Aucune question enregistrée : en cas d\'oubli, seul le support pourra vous aider.') }}</div>
        @endif
        <form method="POST" action="{{ route('settings.security') }}">
            @csrf @method('PUT')
            @include('auth.security-fields', ['bag' => 'security', 'saved' => $securityQuestions])
            <x-field name="security_current_password" :label="__('Mot de passe actuel')" type="password" :required="true" errorBag="security" autocomplete="current-password" />
            <button type="submit" class="btn">{{ __('Enregistrer les questions') }}</button>
        </form>
    </div>
@endsection
