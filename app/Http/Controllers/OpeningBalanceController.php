<?php

namespace App\Http\Controllers;

use App\Models\Meter;
use App\Models\Reading;
use App\Services\BillingService;
use App\Support\SortedTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OpeningBalanceController extends Controller
{
    public function __construct(private BillingService $billing)
    {
    }

    public function index(Request $request): View
    {
        $balances = SortedTable::query(Reading::where('is_opening', true)->with(['meter', 'customer'])
            ->when($request->query('q'), fn ($query, $term) => $query->where(fn ($where) => $where
                ->whereHas('customer', fn ($q) => $q->where('name', 'like', "%{$term}%"))
                ->orWhereHas('meter', fn ($q) => $q->where('number', 'like', "%{$term}%"))))
            ->orderBy('customer_id')
            ->orderBy('meter_id')
            ->orderByDesc('period'), 'dettes', [
                'client' => SortedTable::related('customers', 'name', 'id', 'readings.customer_id'),
                'meter' => SortedTable::related('meters', 'number', 'id', 'readings.meter_id'),
                'period' => 'period',
                'amount' => 'amount',
                'paid' => 'paid_amount',
                'rest' => DB::raw('amount - paid_amount'),
            ]);

        return view('opening-balances.index', [
            'balances' => $balances,
            'totals' => Reading::where('is_opening', true)
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount), 0) as amount, COALESCE(SUM(paid_amount), 0) as paid')
                ->first(),
            'meters' => Meter::with('customer')->whereNotNull('customer_id')->orderBy('number')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules() + [
            'meter_id' => ['required', Rule::exists('meters', 'id')->where('organisation_id', $request->user()->organisation_id)],
        ], [], $this->attributes());

        $meter = Meter::findOrFail($data['meter_id']);

        $this->billing->addOpeningBalance($meter, $data['opening_period'], (float) $data['opening_balance']);

        // Le compteur reste choisi pour saisir facilement un autre mois impayé.
        return redirect()->route('opening-balances.index')
            ->withInput(['meter_id' => $meter->id])
            ->with('success', __('Dette de ').money($data['opening_balance']).__(' enregistrée pour ').period_label($data['opening_period']).__(' sur le compteur ').$meter->number.'.');
    }

    public function edit(Reading $reading): View
    {
        abort_unless($reading->is_opening, 404);

        return view('opening-balances.edit', ['balance' => $reading->load(['meter', 'customer'])]);
    }

    public function update(Request $request, Reading $reading): RedirectResponse
    {
        abort_unless($reading->is_opening, 404);

        $data = $request->validate($this->rules(), [], $this->attributes());

        $this->billing->updateOpeningBalance($reading, $data['opening_period'], (float) $data['opening_balance']);

        return redirect()->route('opening-balances.index')->with('success', __('Dette antérieure modifiée.'));
    }

    public function destroy(Reading $reading): RedirectResponse
    {
        abort_unless($reading->is_opening, 404);

        if ($reading->payments()->exists()) {
            return back()->with('error', __('Cette dette a déjà reçu des paiements. Annulez-les avant de la retirer.'));
        }

        $this->billing->removeOpeningBalance($reading);

        return redirect()->route('opening-balances.index')->with('success', __('Dette antérieure retirée.'));
    }

    private function rules(): array
    {
        return [
            'opening_balance' => ['required', 'numeric', 'gt:0', 'max:999999999', 'decimal:0,3'],
            'opening_period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/', function ($attribute, $value, $fail) {
                if ($value > now()->format('Y-m')) {
                    $fail(__('Le mois de la dette ne peut pas être dans le futur.'));
                }
            }],
        ];
    }

    private function attributes(): array
    {
        return [
            'meter_id' => __('compteur'),
            'opening_balance' => __('montant'),
            'opening_period' => __('mois'),
        ];
    }
}
