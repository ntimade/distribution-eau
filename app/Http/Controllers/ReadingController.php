<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceFee;
use App\Models\Meter;
use App\Models\Reading;
use App\Services\BillingService;
use App\Support\SortedTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReadingController extends Controller
{
    private const PERIOD_RULE = 'regex:/^\d{4}-(0[1-9]|1[0-2])$/';

    public function __construct(private BillingService $billing)
    {
    }

    public function index(Request $request): View
    {
        $period = $this->period($request);

        if ($request->user()->isReader()) {
            return $this->readerIndex($request, $period);
        }

        return view('readings.index', [
            'period' => $period,
            'justSaved' => session('notify_reading') ? Reading::with(['customer', 'meter'])->find(session('notify_reading')) : null,
            'readings' => SortedTable::query(Reading::with(['meter', 'customer'])->real()->where('period', $period)->orderByDesc('id'), 'releves', [
                'meter' => SortedTable::related('meters', 'number', 'id', 'readings.meter_id'),
                'client' => SortedTable::related('customers', 'name', 'id', 'readings.customer_id'),
                'previous' => 'previous_index',
                'current' => 'current_index',
                'consumption' => 'consumption',
                'maintenance' => 'maintenance_fee',
                'amount' => 'amount',
                'due' => 'due_date',
            ]),
            'totals' => Reading::real()->where('period', $period)
                ->selectRaw('COALESCE(SUM(consumption), 0) as volume, COALESCE(SUM(amount), 0) as billed')->first(),
            'pendingMeters' => SortedTable::query(Meter::with('customer')->where('is_active', true)->whereNotNull('customer_id')
                ->whereDoesntHave('readings', fn ($q) => $q->real()->where('period', $period))
                ->orderBy('number'), 'attente', [
                    'number' => 'number',
                    'client' => SortedTable::related('customers', 'name', 'id', 'meters.customer_id'),
                ]),
        ]);
    }

    // Le releveur voit les compteurs de ses zones, choisit le mois et renseigne les index.
    private function readerIndex(Request $request, string $period): View
    {
        $search = trim((string) $request->query('q'));

        $meters = Meter::with(['customer', 'zone'])->accessibleBy($request->user())
            ->where('is_active', true)->whereNotNull('customer_id')
            ->when($search !== '', fn ($query) => $query->where(fn ($match) => $match
                ->where('number', 'like', "%{$search}%")
                ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%"))));

        $pendingCount = (clone $meters)->whereDoesntHave('readings', fn ($q) => $q->real()->where('period', $period))->count();

        return view('readings.reader', [
            'period' => $period,
            'search' => $search,
            'pendingCount' => $pendingCount,
            'justSaved' => session('notify_reading') ? Reading::with(['customer', 'meter'])->accessibleBy($request->user())->find(session('notify_reading')) : null,
            'meters' => SortedTable::query($meters
                ->addSelect([
                    'period_index' => Reading::real()->select('current_index')->whereColumn('readings.meter_id', 'meters.id')
                        ->where('period', $period)->limit(1),
                    'previous_index' => Reading::real()->select('current_index')->whereColumn('readings.meter_id', 'meters.id')
                        ->where('period', '<', $period)->orderByDesc('period')->limit(1),
                ])
                // Ce qui reste à relever d'abord.
                ->orderByRaw('period_index IS NULL DESC')->orderBy('number'), 'compteurs', [
                    'number' => 'number',
                    'client' => SortedTable::related('customers', 'name', 'id', 'meters.customer_id'),
                ]),
        ]);
    }

    public function create(Request $request): View
    {
        $meters = Meter::with('customer')->where('is_active', true)->whereNotNull('customer_id')
            ->addSelect(['last_index' => Reading::real()->select('current_index')->whereColumn('readings.meter_id', 'meters.id')
                ->orderByDesc('period')->limit(1)])
            ->orderBy('number')->get();

        return view('readings.create', [
            'meters' => $meters,
            'selectedMeter' => $request->query('meter_id'),
            'period' => $this->period($request),
            'maintenanceFees' => MaintenanceFee::pluck('amount', 'period'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $organisationId = $request->user()->organisation_id;

        $data = $request->validate([
            'meter_id' => ['required', Rule::exists('meters', 'id')->where('organisation_id', $organisationId)],
            'period' => ['required', self::PERIOD_RULE, function ($attribute, $value, $fail) {
                if ($value > now()->format('Y-m')) {
                    $fail(__('Le mois du relevé ne peut pas être dans le futur.'));
                }
            }],
            'current_index' => ['required', 'numeric', 'min:0', 'max:999999999', 'decimal:0,3'],
            'read_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $reading = $this->billing->recordReading(
            Meter::accessibleBy($request->user())->findOrFail($data['meter_id']), $data['period'], (float) $data['current_index'], $data['read_at'], $data['notes'] ?? null
        );

        return redirect()->route('readings.index', ['period' => $reading->period])->with(
            'success',
            "Relevé du compteur {$reading->meter->number} : ".qty($reading->consumption).__(' m³, soit ').money($reading->amount)
            .((float) $reading->maintenance_fee > 0 ? __(' dont ').money($reading->maintenance_fee).__(' de frais d\'entretien.') : '.')
        )->with('notify_reading', $reading->id);
    }

    public function show(Reading $reading): View
    {
        return view('readings.show', [
            'reading' => $reading->load(['meter', 'customer']),
            'payments' => SortedTable::query($reading->payments()->with('receipt')->latest('paid_at')->latest('id'), 'paiements', [
                'date' => 'paid_at',
                'method' => 'method',
                'reference' => 'reference',
                'amount' => 'amount',
            ]),
        ]);
    }

    public function edit(Reading $reading): View|RedirectResponse
    {
        if (! $reading->isEditable()) {
            return redirect()->route('readings.show', $reading)
                ->with('error', __('Une dette antérieure se modifie depuis la rubrique Dettes antérieures.'));
        }

        return view('readings.edit', ['reading' => $reading->load(['meter', 'customer'])]);
    }

    public function update(Request $request, Reading $reading): RedirectResponse
    {
        $data = $request->validate([
            'period' => ['nullable', self::PERIOD_RULE, function ($attribute, $value, $fail) {
                if ($value > now()->format('Y-m')) {
                    $fail(__('Le mois du relevé ne peut pas être dans le futur.'));
                }
            }],
            'current_index' => ['required', 'numeric', 'min:0', 'max:999999999', 'decimal:0,3'],
            'read_at' => ['required', 'date', 'before_or_equal:today'],
            'due_date' => ['nullable', 'date'],
            'unit_price' => ['nullable', 'numeric', 'gt:0', 'max:999999999', 'decimal:0,3'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [], [
            'due_date' => __('échéance'),
            'unit_price' => __('prix du m³'),
        ]);

        $this->billing->correctReading($reading, $data);

        return redirect()->route('readings.show', $reading)->with('success', __('Relevé corrigé. Les montants ont été recalculés.'));
    }

    public function destroy(Reading $reading): RedirectResponse
    {
        if (! $reading->isDeletable()) {
            return back()->with('error', __('Seul le dernier relevé d\'un compteur, sans paiement, peut être supprimé.'));
        }

        $period = $reading->period;
        // Les paiements annulés restent en base et bloqueraient la suppression.
        $reading->payments()->onlyTrashed()->forceDelete();
        $reading->delete();

        return redirect()->route('readings.index', compact('period'))->with('success', __('Relevé supprimé.'));
    }

    private function period(Request $request): string
    {
        $period = (string) $request->query('period');

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) ? $period : now()->format('Y-m');
    }
}
