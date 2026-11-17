<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceFee;
use App\Models\MonthlyDueDate;
use App\Models\Organisation;
use App\Models\Reading;
use App\Services\BillingService;
use App\Services\PasswordRecovery;
use App\Support\PhoneNumber;
use App\Support\SortedTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $organisation = $request->user()->organisation;
        $currentPeriod = now()->format('Y-m');
        $maintenanceFees = MaintenanceFee::orderByDesc('period')->get();
        $invoiceCounts = Reading::real()->selectRaw('period, COUNT(*) as total')->groupBy('period')->pluck('total', 'period');
        $invoicesOf = fn ($row) => (int) ($invoiceCounts[$row->period] ?? 0);

        return view('settings', [
            'organisation' => $organisation,
            'securityQuestions' => $request->user()->securityAnswers()->orderBy('id')->pluck('question')->all(),
            'currencies' => config('currencies'),
            'currencyLocked' => $this->currencyLocked($organisation),
            'currentPeriod' => $currentPeriod,
            'currentFee' => $maintenanceFees->firstWhere('period', $currentPeriod),
            'maintenanceFees' => SortedTable::collection($maintenanceFees, 'frais', [
                'month' => fn ($fee) => $fee->period,
                'amount' => fn ($fee) => (float) $fee->amount,
                'invoices' => $invoicesOf,
            ]),
            'monthlyDueDates' => SortedTable::collection(MonthlyDueDate::orderByDesc('period')->get(), 'echeances', [
                'month' => fn ($monthly) => $monthly->period,
                'due' => fn ($monthly) => $monthly->due_date->toDateString(),
                'invoices' => $invoicesOf,
            ]),
            'invoiceCounts' => $invoiceCounts,
        ]);
    }

    public function updateBillingDelays(Request $request): RedirectResponse
    {
        $request->user()->organisation->update($request->validate([
            'payment_due_days' => ['required', 'integer', 'min:1', 'max:365'],
            'cutoff_grace_days' => ['required', 'integer', 'min:1', 'max:365'],
        ], [], [
            'payment_due_days' => __('délai de paiement'),
            'cutoff_grace_days' => __('délai avant coupure'),
        ]));

        return back()->with('success', __('Délais enregistrés. Ils s\'appliquent aux prochaines factures et aux prochains avis.'));
    }

    public function updateMonthlyDue(Request $request, BillingService $billing): RedirectResponse
    {
        $data = $request->validate([
            'monthly_period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'monthly_due_date' => ['required', 'date', function ($attribute, $value, $fail) use ($request) {
                if ($value < $request->input('monthly_period').'-01') {
                    $fail(__('L\'échéance ne peut pas être avant le début du mois facturé.'));
                }
            }],
        ], [], [
            'monthly_period' => __('mois'),
            'monthly_due_date' => __('date d\'échéance'),
        ]);

        $count = $billing->setMonthlyDueDate($data['monthly_period'], $data['monthly_due_date']);

        return back()->with('success', __('Échéance ').of_period($data['monthly_period']).__(' enregistrée : ').$count.__(' facture(s) mise(s) à jour.'));
    }

    public function destroyMonthlyDue(BillingService $billing, MonthlyDueDate $monthlyDueDate): RedirectResponse
    {
        $billing->setMonthlyDueDate($monthlyDueDate->period, null);

        return back()->with('success', __('Délai de paiement habituel rétabli pour ').period_label($monthlyDueDate->period).'.');
    }

    public function updateLogo(Request $request): RedirectResponse
    {
        // Pas de SVG : il peut contenir du code exécuté par le navigateur.
        $request->validate([
            'logo' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ], [], ['logo' => 'logo']);

        $organisation = $request->user()->organisation;
        $previous = $organisation->logo_path;

        $organisation->update(['logo_path' => $request->file('logo')->store('logos', 'local')]);

        if ($previous) {
            Storage::disk('local')->delete($previous);
        }

        return back()->with('success', __('Logo enregistré.'));
    }

    public function destroyLogo(Request $request): RedirectResponse
    {
        $organisation = $request->user()->organisation;

        if ($organisation->logo_path) {
            Storage::disk('local')->delete($organisation->logo_path);
            $organisation->update(['logo_path' => null]);
        }

        return back()->with('success', __('Logo retiré.'));
    }

    // Toujours le logo de l'organisation connectée : l'adresse ne désigne aucun fichier.
    public function showLogo(Request $request): StreamedResponse
    {
        $path = $request->user()->organisation->logo_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    public function updateMaintenance(Request $request, BillingService $billing): RedirectResponse
    {
        $data = $request->validate([
            'maintenance_period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'maintenance_amount' => ['nullable', 'required_if:maintenance_enabled,1', 'numeric', 'gt:0', 'max:999999999', 'decimal:0,3'],
        ], [
            'maintenance_amount.required_if' => __('Indiquez le montant des frais d\'entretien.'),
        ], [
            'maintenance_period' => __('mois'),
            'maintenance_amount' => __('montant'),
        ]);

        $period = $data['maintenance_period'];

        if (! $request->boolean('maintenance_enabled')) {
            if (! MaintenanceFee::where('period', $period)->exists()) {
                return back()->with('error', __('Cochez « Appliquer les frais d\'entretien » pour les ajouter à ').period_label($period).'.');
            }

            return $this->removeFee($billing, $period);
        }

        $count = $billing->setMaintenanceFee($period, (float) $data['maintenance_amount']);

        return back()->with('success', __('Frais d\'entretien de ').money($data['maintenance_amount']).__(' pour ').period_label($period)
            .' : '.$count.__(' facture(s) déjà saisie(s) mise(s) à jour. Les relevés de ce mois saisis ensuite les incluront.'));
    }

    public function destroyMaintenance(BillingService $billing, MaintenanceFee $maintenanceFee): RedirectResponse
    {
        return $this->removeFee($billing, $maintenanceFee->period);
    }

    private function removeFee(BillingService $billing, string $period): RedirectResponse
    {
        $billing->setMaintenanceFee($period, null);

        return back()->with('success', __('Frais d\'entretien retirés des factures ').of_period($period).'.');
    }

    // Les montants déjà facturés sont exprimés dans la devise d'origine.
    private function currencyLocked(Organisation $organisation): bool
    {
        return $organisation->readings()->exists() || MaintenanceFee::exists();
    }

    public function update(Request $request): RedirectResponse
    {
        $organisation = $request->user()->organisation;

        $currencyRule = $this->currencyLocked($organisation)
            ? Rule::in([$organisation->currency])
            : Rule::in(array_keys(config('currencies')));

        $previousCountry = $organisation->phone_country;

        $organisation->update($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'phone_country' => ['nullable', Rule::in(array_keys(config('phone_countries')))],
            'address' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', $currencyRule],
            'price_per_m3' => ['required', 'numeric', 'gt:0', 'max:999999999', 'decimal:0,3'],
        ], [], ['phone_country' => __('pays')]));

        $response = back()->with('success', __('Paramètres enregistrés.'));

        if (! $organisation->phone_country || $organisation->phone_country === $previousCountry) {
            return $response;
        }

        // Les numéros déjà complets ne changent pas : seuls ceux sans indicatif le reçoivent.
        $result = PhoneNumber::normalizeOrganisation($organisation->id, $organisation->phone_country);

        return $result['invalid'] > 0
            ? $response->with('error', __(':v1 numéro(s) de client illisible(s) : ils sont marqués « À corriger » dans la liste des clients.', ['v1' => $result['invalid']]))
            : $response;
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->forceFill(['password' => $validated['password'], 'must_change_password' => false])->save();

        return back()->with('success', __('Mot de passe modifié.'));
    }

    public function updateSecurityQuestions(Request $request, PasswordRecovery $recovery): RedirectResponse
    {
        $validated = $request->validateWithBag('security', [
            'security_current_password' => ['required', 'current_password'],
        ] + $recovery->rules(), $recovery->messages(), $recovery->attributes());

        $recovery->saveAnswers($request->user(), $validated['security']);

        return redirect()->to(route('settings.edit').'#section-questions')->with('success', __('Questions de sécurité enregistrées.'));
    }
}
