<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Meter;
use App\Models\Zone;
use App\Services\BillingService;
use App\Support\SortedTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MeterController extends Controller
{
    public function __construct(private BillingService $billing)
    {
    }

    public function index(Request $request): View
    {
        $meters = SortedTable::query(Meter::with(['customer', 'zone'])
            ->withMax(['readings' => fn ($q) => $q->real()], 'period')
            ->when($request->query('q'), fn ($q, $term) => $q->where('number', 'like', "%{$term}%"))
            ->when($request->query('zone_id'), fn ($q, $id) => $q->where('zone_id', $id))
            ->orderBy('number'), 'compteurs', [
                'number' => 'number',
                'client' => SortedTable::related('customers', 'name', 'id', 'meters.customer_id'),
                'zone' => SortedTable::related('zones', 'name', 'id', 'meters.zone_id'),
                'last' => 'readings_max_period',
                'map' => DB::raw('latitude IS NULL'),
                'active' => 'is_active',
            ]);

        return view('meters.index', ['meters' => $meters, 'zones' => Zone::orderBy('name')->get()]);
    }

    public function create(Request $request): View
    {
        return $this->form(new Meter([
            'is_active' => true,
            'initial_index' => 0,
            'customer_id' => $request->query('customer_id'),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $meter = Meter::create($this->validated($request));

        return redirect()->route('meters.show', $meter)->with('success', __('Compteur ajouté.'));
    }

    public function show(Meter $meter): View
    {
        return view('meters.show', [
            'meter' => Meter::withPaymentStatus()->with(['customer', 'zone', 'replacement.newMeter', 'replacedFrom.oldMeter'])->findOrFail($meter->id),
            'readings' => SortedTable::query($meter->readings()->with('customer')->orderByDesc('period'), 'releves', [
                'period' => 'period',
                'client' => SortedTable::related('customers', 'name', 'id', 'readings.customer_id'),
                'previous' => 'previous_index',
                'current' => 'current_index',
                'consumption' => 'consumption',
                'amount' => 'amount',
                'due' => 'due_date',
            ]),
        ]);
    }

    public function edit(Meter $meter): View
    {
        return $this->form($meter);
    }

    public function update(Request $request, Meter $meter): RedirectResponse
    {
        $data = $this->validated($request, $meter);

        if (($data['initial_index'] ?? null) === null) {
            unset($data['initial_index']);
        }

        // Un compteur remplacé ne se réactive qu'en annulant le remplacement.
        if ($meter->replacement()->exists()) {
            $data['is_active'] = false;
        }

        DB::transaction(function () use ($meter, $data) {
            // Corriger l'index de départ recalcule aussi le premier relevé.
            if (isset($data['initial_index']) && (float) $data['initial_index'] !== (float) $meter->initial_index) {
                $this->billing->correctInitialIndex($meter, (float) $data['initial_index']);
            }

            $meter->update($data);
        });

        return redirect()->route('meters.show', $meter)->with('success', __('Compteur modifié.'));
    }

    public function destroy(Meter $meter): RedirectResponse
    {
        if ($meter->readings()->exists()) {
            return back()->with('error', __('Ce compteur a des relevés ou une dette antérieure : désactivez-le plutôt que de le supprimer.'));
        }

        if ($meter->replacement()->exists() || $meter->replacedFrom()->exists()) {
            return back()->with('error', __('Ce compteur fait partie d\'un remplacement : annulez d\'abord le remplacement.'));
        }

        $meter->delete();

        return redirect()->route('meters.index')->with('success', __('Compteur supprimé.'));
    }

    private function form(Meter $meter): View
    {
        return view('meters.form', [
            'meter' => $meter,
            'customers' => Customer::orderBy('name')->get(),
            'zones' => Zone::orderBy('name')->get(),
            'hasReadings' => $meter->exists && $meter->readings()->real()->exists(),
        ]);
    }

    private function validated(Request $request, ?Meter $meter = null): array
    {
        $organisationId = $request->user()->organisation_id;

        $data = $request->validate([
            'number' => ['required', 'string', 'max:50', Rule::unique('meters')
                ->where('organisation_id', $organisationId)->ignore($meter)],
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('organisation_id', $organisationId)],
            'zone_id' => ['nullable', Rule::exists('zones', 'id')->where('organisation_id', $organisationId)],
            'initial_index' => [$meter ? 'nullable' : 'required', 'numeric', 'min:0', 'max:999999999', 'decimal:0,3'],
            'installed_at' => ['nullable', 'date'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
        ]);

        return $data + ['is_active' => $request->boolean('is_active')];
    }
}
