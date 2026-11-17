<?php

namespace App\Http\Controllers;

use App\Models\Meter;
use App\Models\MeterReplacement;
use App\Services\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MeterReplacementController extends Controller
{
    public function __construct(private BillingService $billing)
    {
    }

    public function create(Meter $meter): View
    {
        return view('meters.replace', ['meter' => $meter->load('customer'), 'lastIndex' => $meter->lastIndex()]);
    }

    public function store(Request $request, Meter $meter): RedirectResponse
    {
        $data = $request->validate([
            'replaced_on' => ['required', 'date', 'before_or_equal:today'],
            'old_final_index' => ['required', 'numeric', 'min:0', 'max:999999999', 'decimal:0,3'],
            'number' => ['required', 'string', 'max:50', Rule::unique('meters')->where('organisation_id', $request->user()->organisation_id)],
            'initial_index' => ['required', 'numeric', 'min:0', 'max:999999999', 'decimal:0,3'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [], $this->attributes());

        $replacement = $this->billing->replaceMeter($meter, $data);

        return redirect()->route('meters.show', $replacement->new_meter_id)->with(
            'success',
            __('Compteur ').$meter->number.__(' remplacé. Ses ').qty($replacement->old_consumption).__(' m³ seront ajoutés à la prochaine facture de ce compteur.')
        );
    }

    public function edit(MeterReplacement $replacement): View
    {
        return view('meters.replacement-edit', ['replacement' => $replacement->load(['oldMeter', 'newMeter', 'reading'])]);
    }

    public function update(Request $request, MeterReplacement $replacement): RedirectResponse
    {
        $data = $request->validate([
            'replaced_on' => ['required', 'date', 'before_or_equal:today'],
            'old_final_index' => ['required', 'numeric', 'min:0', 'max:999999999', 'decimal:0,3'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [], $this->attributes());

        $this->billing->correctReplacement($replacement, $data);

        return redirect()->route('meters.show', $replacement->new_meter_id)->with('success', __('Remplacement corrigé. Les montants ont été recalculés.'));
    }

    public function destroy(MeterReplacement $replacement): RedirectResponse
    {
        $this->billing->cancelReplacement($replacement);

        return redirect()->route('meters.show', $replacement->old_meter_id)->with('success', __('Remplacement annulé : l\'ancien compteur est de nouveau actif.'));
    }

    private function attributes(): array
    {
        return [
            'replaced_on' => __('date du remplacement'),
            'old_final_index' => __('index final'),
            'number' => __('numéro du nouveau compteur'),
            'initial_index' => __('index de pose'),
            'notes' => __('observation'),
        ];
    }
}
