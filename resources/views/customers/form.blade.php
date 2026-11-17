@extends('layouts.app')
@section('title', $customer->exists ? __('Modifier le client') : __('Nouveau client'))

@section('content')
    <div class="page-head"><h1>{{ $customer->exists ? __('Modifier le client') : __('Nouveau client') }}</h1></div>
    <div class="card narrow">
        <form method="POST" action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}">
            @csrf
            @if ($customer->exists) @method('PUT') @endif
            <x-field name="name" :label="__('Nom')" :value="$customer->name" :required="true" autofocus />
            <x-field name="gender" :label="__('Sexe')" :help="__('Sert à écrire Monsieur ou Madame dans les messages.')">
                <select id="gender" name="gender">
                    <option value="">{{ __('Non renseigné (Monsieur/Madame)') }}</option>
                    <option value="M" @selected(old('gender', $customer->gender) === 'M')>{{ __('Homme (Monsieur)') }}</option>
                    <option value="F" @selected(old('gender', $customer->gender) === 'F')>{{ __('Femme (Madame)') }}</option>
                </select>
            </x-field>
            <div class="form-grid">
                <x-field name="phone" :label="__('Téléphone')" type="tel" :value="$customer->phone" :help="__('L\'indicatif du pays est ajouté automatiquement.')" />
                <x-field name="email" :label="__('E-mail')" type="email" :value="$customer->email" />
            </div>
            <x-field name="address" :label="__('Adresse')" :value="$customer->address" />
            <x-field name="price_per_m3" :label="__('Prix du m³ de ce client')" type="number" step="0.001" min="0.001" :value="$customer->price_per_m3 === null ? null : (float) $customer->price_per_m3"
                     :help="__('Laissez vide pour le tarif général. S\'applique aux prochains relevés.')" />

            @unless ($customer->exists)
                <x-field name="has_previous_debt" :label="__('Ce client a-t-il une dette antérieure ?')" :required="true">
                    <div class="choices">
                        <label class="choice"><input type="radio" name="has_previous_debt" value="no" required @checked(old('has_previous_debt') === 'no')> {{ __('Non') }}</label>
                        <label class="choice"><input type="radio" name="has_previous_debt" value="yes" required @checked(old('has_previous_debt') === 'yes')> {{ __('Oui') }}</label>
                    </div>
                </x-field>

                <div id="previous-debt" class="subpanel">
                    <x-field name="meter_status" :label="__('Le compteur concerné par cette dette')" :required="true">
                        <div class="choices">
                            <label class="choice"><input type="radio" name="meter_status" value="active" @checked(old('meter_status', 'active') === 'active')> {{ __('Est en service') }}</label>
                            <label class="choice"><input type="radio" name="meter_status" value="out" @checked(old('meter_status') === 'out')> {{ __('Est hors service ou n\'existe plus') }}</label>
                        </div>
                    </x-field>
                    <x-field name="meter_number" :label="__('Numéro du compteur')"
                             :help="__('La dette est rattachée à ce compteur, créé avec le client. S\'il est hors service et que vous ne connaissez pas son numéro, laissez vide : un numéro provisoire sera créé.')" />

                    <p class="subpanel-title"><strong>{{ __('Mois impayés') }}</strong>
                        <span class="soft small">{{ __('Une ligne par mois impayé. Ne saisissez pas les mois déjà payés.') }}</span></p>
                    @error('debts')<p class="danger-text small">{{ $message }}</p>@enderror
                    @error('opening_balance')<p class="danger-text small">{{ $message }}</p>@enderror
                    @error('opening_period')<p class="danger-text small">{{ $message }}</p>@enderror

                    <div id="debt-rows">
                        @foreach (old('debts', [['period' => now()->subMonthNoOverflow()->format('Y-m'), 'amount' => '']]) as $index => $debt)
                            <div class="form-grid debt-row">
                                <div class="field">
                                    <label>{{ __('Mois impayé') }} <span class="req">*</span></label>
                                    <input type="month" name="debts[{{ $index }}][period]" value="{{ $debt['period'] ?? '' }}" max="{{ now()->format('Y-m') }}" required>
                                    @error("debts.$index.period")<div class="error">{{ $message }}</div>@enderror
                                </div>
                                <div class="field">
                                    <label>{{ __('Montant dû') }} <span class="req">*</span></label>
                                    <input type="number" name="debts[{{ $index }}][amount]" value="{{ $debt['amount'] ?? '' }}" step="any" min="0" required>
                                    @error("debts.$index.amount")<div class="error">{{ $message }}</div>@enderror
                                </div>
                                <div class="field debt-remove">
                                    <button type="button" class="btn danger small" data-remove-debt>{{ __('Retirer ce mois') }}</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <p><button type="button" class="btn ghost small" id="add-debt">{{ __('Ajouter un autre mois') }}</button></p>
                </div>
            @endunless

            <div class="form-actions">
                <button type="submit" class="btn">{{ __('Enregistrer') }}</button>
                <a class="btn ghost" href="{{ $customer->exists ? route('customers.show', $customer) : route('customers.index') }}">{{ __('Annuler') }}</a>
            </div>
        </form>
    </div>
@endsection

@unless ($customer->exists)
    @push('scripts')
        <script>
            (function () {
                var section = document.getElementById('previous-debt');
                var rows = document.getElementById('debt-rows');
                var nextIndex = rows.children.length;

                // Champs désactivés quand ils sont masqués : ils ne bloquent pas l'envoi du formulaire.
                function refresh() {
                    var yes = document.querySelector('input[name="has_previous_debt"][value="yes"]').checked;
                    section.hidden = !yes;
                    section.querySelectorAll('input').forEach(function (input) { input.disabled = !yes; });
                    document.getElementById('meter_number').required = yes
                        && document.querySelector('input[name="meter_status"][value="active"]').checked;
                    rows.querySelectorAll('[data-remove-debt]').forEach(function (button) {
                        button.hidden = rows.children.length === 1;
                    });
                }

                document.getElementById('add-debt').addEventListener('click', function () {
                    var row = rows.lastElementChild.cloneNode(true);
                    row.querySelectorAll('.error').forEach(function (error) { error.remove(); });
                    row.querySelectorAll('input').forEach(function (input) {
                        input.name = input.name.replace(/debts\[\d+\]/, 'debts[' + nextIndex + ']');
                        input.value = '';
                    });
                    nextIndex++;
                    rows.appendChild(row);
                    refresh();
                    row.querySelector('input').focus();
                });

                rows.addEventListener('click', function (event) {
                    if (event.target.matches('[data-remove-debt]') && rows.children.length > 1) {
                        event.target.closest('.debt-row').remove();
                        refresh();
                    }
                });

                document.querySelectorAll('input[name="has_previous_debt"], input[name="meter_status"]').forEach(function (radio) {
                    radio.addEventListener('change', refresh);
                });
                refresh();
            })();
        </script>
    @endpush
@endunless
