<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Meter;
use App\Models\Reading;
use App\Models\Receipt;
use App\Models\Zone;
use App\Services\BillingService;
use App\Support\PhoneNumber;
use App\Support\SortedTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $customers = SortedTable::query(Customer::query()
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', '%'.preg_replace('/[\s().\-+]/', '', $term).'%')))
            ->withCount('meters')
            ->addSelect(['unpaid' => Reading::selectRaw('COALESCE(SUM(amount - paid_amount), 0)')
                ->whereColumn('readings.customer_id', 'customers.id')])
            ->orderBy('name'), 'clients', [
                'name' => 'name',
                'phone' => 'phone',
                'price' => 'price_per_m3',
                'meters' => 'meters_count',
                'unpaid' => 'unpaid',
            ]);

        return view('customers.index', [
            'customers' => $customers,
            'zones' => Zone::orderBy('name')->get(),
        ]);
    }

    // Applique un prix du m³ à plusieurs clients ; un prix vide les remet au tarif général.
    public function bulkPrice(Request $request, BillingService $billing): RedirectResponse
    {
        $organisationId = $request->user()->organisation_id;

        $data = $request->validate([
            'target' => ['required', Rule::in(['selected', 'zone', 'all'])],
            'customer_ids' => ['required_if:target,selected', 'array'],
            'customer_ids.*' => ['integer'],
            'zone_id' => ['required_if:target,zone', 'nullable', Rule::exists('zones', 'id')->where('organisation_id', $organisationId)],
            'price_per_m3' => ['nullable', 'numeric', 'gt:0', 'max:999999999', 'decimal:0,3'],
            'apply_period' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ], [
            'customer_ids.required_if' => __('Cochez au moins un client dans la liste.'),
            'zone_id.required_if' => __('Choisissez une zone.'),
        ], [
            'price_per_m3' => __('prix du m³'),
            'apply_period' => __('mois'),
        ]);

        $ids = match ($data['target']) {
            'selected' => Customer::whereIn('id', $data['customer_ids'] ?? [])->pluck('id'),
            'zone' => Customer::whereHas('meters', fn ($q) => $q->where('zone_id', $data['zone_id']))->pluck('id'),
            default => Customer::pluck('id'),
        };

        if ($ids->isEmpty()) {
            return back()->with('error', __('Aucun client ne correspond à ce choix.'));
        }

        $price = $data['price_per_m3'] ?? null;
        $message = $ids->count().__(' client(s) ').($price === null ? __('remis au tarif général.') : __('au prix de ').money($price).__(' le m³.'));

        DB::transaction(function () use ($ids, $price, $data, $billing, &$message) {
            Customer::whereIn('id', $ids)->update(['price_per_m3' => $price]);

            if (! empty($data['apply_period'])) {
                $result = $billing->repriceCustomers($ids, $data['apply_period']);
                $message .= __(' Factures ').of_period($data['apply_period']).__(' recalculées : ').$result['updated'].'.';

                if ($result['skipped'] > 0) {
                    $message .= __(' Laissées telles quelles (déjà trop payées ou sans prix) : ').$result['skipped'].'.';
                }
            }
        });

        return back()->with('success', $message);
    }

    public function create(): View
    {
        return view('customers.form', ['customer' => new Customer]);
    }

    public function store(Request $request, BillingService $billing): RedirectResponse
    {
        $data = $this->validated($request, [
            'has_previous_debt' => ['required', Rule::in(['yes', 'no'])],
            'debts' => ['exclude_unless:has_previous_debt,yes', 'required', 'array', 'min:1'],
            'debts.*.period' => ['exclude_unless:has_previous_debt,yes', 'required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/', 'distinct', function ($attribute, $value, $fail) {
                if ($value > now()->format('Y-m')) {
                    $fail(__('Le mois de la dette ne peut pas être dans le futur.'));
                }
            }],
            'debts.*.amount' => ['exclude_unless:has_previous_debt,yes', 'required', 'numeric', 'gt:0', 'max:999999999', 'decimal:0,3'],
            'meter_status' => ['exclude_unless:has_previous_debt,yes', 'nullable', Rule::in(['active', 'out'])],
            'meter_number' => ['exclude_unless:has_previous_debt,yes', 'required_unless:meter_status,out', 'nullable', 'string', 'max:50', Rule::unique('meters', 'number')
                ->where('organisation_id', $request->user()->organisation_id)],
        ]);

        // Tout est créé ensemble : une dette refusée n'ajoute ni client ni compteur.
        $createdMeter = null;
        $customer = DB::transaction(function () use ($data, $billing, &$createdMeter) {
            $customer = Customer::create($data);

            if ($data['has_previous_debt'] === 'yes') {
                $meterOut = ($data['meter_status'] ?? 'active') === 'out';

                $meter = Meter::create([
                    'customer_id' => $customer->id,
                    'number' => $data['meter_number'] ?? $this->provisionalMeterNumber($customer),
                    'initial_index' => 0,
                    'is_active' => ! $meterOut,
                ]);

                $createdMeter = $meter;

                foreach ($data['debts'] as $debt) {
                    $billing->addOpeningBalance($meter, $debt['period'], (float) $debt['amount']);
                }
            }

            return $customer;
        });

        $message = __('Client ajouté.');

        if ($createdMeter) {
            $debt = count($data['debts']).__(' mois de dette antérieure, soit ').money(collect($data['debts'])->sum('amount')).'.';

            $message = $createdMeter->is_active
                ? __('Client ajouté avec le compteur ').$createdMeter->number.__(' et ').$debt.__(' Pensez à renseigner l\'index de départ sur la fiche du compteur.')
                : __('Client ajouté avec le compteur hors service ').$createdMeter->number.__(' et ').$debt.__(' Quand un nouveau compteur sera posé, ajoutez-le pour ce client.');
        }

        return redirect()->route('customers.show', $customer)->with('success', $message);
    }

    private function provisionalMeterNumber(Customer $customer): string
    {
        $number = 'SANS-NUMERO-'.$customer->id;

        for ($suffix = 2; Meter::where('number', $number)->exists(); $suffix++) {
            $number = 'SANS-NUMERO-'.$customer->id.'-'.$suffix;
        }

        return $number;
    }

    public function show(Customer $customer): View
    {
        return view('customers.show', [
            'customer' => $customer,
            'meters' => SortedTable::query($customer->meters()->with('zone')->orderBy('number'), 'compteurs', [
                'number' => 'number',
                'zone' => SortedTable::related('zones', 'name', 'id', 'meters.zone_id'),
                'active' => 'is_active',
            ]),
            'readings' => SortedTable::query($customer->readings()->with(['meter', 'payments'])->orderByDesc('period'), 'factures', [
                'period' => 'period',
                'meter' => SortedTable::related('meters', 'number', 'id', 'readings.meter_id'),
                'consumption' => 'consumption',
                'amount' => 'amount',
                'paid' => 'paid_amount',
                'rest' => DB::raw('amount - paid_amount'),
                'due' => 'due_date',
            ]),
            'unpaid' => $customer->readings()->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as total')->value('total'),
            // La dette du client compteur par compteur : chaque compteur reste séparé.
            'debts' => Reading::with('meter')->unpaid()->where('customer_id', $customer->id)
                ->selectRaw('meter_id, COUNT(*) as months, SUM(amount - paid_amount) as total')
                ->groupBy('meter_id')->get()
                ->sortBy(fn (Reading $row) => $row->meter->number)->values(),
            'receipts' => Receipt::with('payments.reading.meter')->where('customer_id', $customer->id)
                ->latest('paid_at')->latest('id')->limit(10)->get(),
        ]);
    }

    public function edit(Customer $customer): View
    {
        return view('customers.form', compact('customer'));
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $customer->update($this->validated($request));

        return redirect()->route('customers.show', $customer)->with('success', __('Client modifié.'));
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        if ($customer->meters()->exists() || $customer->readings()->exists()) {
            return back()->with('error', __('Ce client a des compteurs ou des relevés : il ne peut pas être supprimé.'));
        }

        $customer->delete();

        return redirect()->route('customers.index')->with('success', __('Client supprimé.'));
    }

    private function validated(Request $request, array $extraRules = []): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', 'in:M,F'],
            'phone' => ['nullable', 'string', 'max:50', function ($attribute, $value, $fail) {
                if (! PhoneNumber::isValid($value)) {
                    $fail(__('Saisissez un seul numéro de téléphone, avec des chiffres uniquement.'));
                }
            }],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'price_per_m3' => ['nullable', 'numeric', 'gt:0', 'max:999999999', 'decimal:0,3'],
        ] + $extraRules, [
            'has_previous_debt.required' => __('Indiquez si ce client a une dette antérieure.'),
            'debts.*.period.distinct' => __('Ce mois est saisi deux fois.'),
        ], [
            'debts.*.period' => __('mois'),
            'debts.*.amount' => __('montant'),
            'price_per_m3' => __('prix du m³'),
            'meter_number' => __('numéro du compteur'),
        ]);

        $data['phone'] = PhoneNumber::forStorage($data['phone'] ?? null, $request->user()->organisation->phone_country);

        return $data;
    }
}
