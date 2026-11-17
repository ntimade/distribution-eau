<?php

namespace App\Http\Controllers;

use App\Models\Zone;
use App\Support\SortedTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ZoneController extends Controller
{
    public function index(): View
    {
        return view('zones.index', ['zones' => SortedTable::query(Zone::withCount('meters')->orderBy('name'), 'zones', [
            'name' => 'name',
            'description' => 'description',
            'meters' => 'meters_count',
        ])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Zone::create($this->validated($request));

        return back()->with('success', __('Zone ajoutée.'));
    }

    public function edit(Zone $zone): View
    {
        return view('zones.edit', compact('zone'));
    }

    public function update(Request $request, Zone $zone): RedirectResponse
    {
        $zone->update($this->validated($request, $zone));

        return redirect()->route('zones.index')->with('success', __('Zone modifiée.'));
    }

    public function destroy(Zone $zone): RedirectResponse
    {
        if (DB::table('reader_zone')->where('zone_id', $zone->id)->exists()) {
            return back()->with('error', __('Un releveur est affecté à cette zone : changez d\'abord sa zone.'));
        }

        $zone->delete();

        return redirect()->route('zones.index')->with('success', __('Zone supprimée. Ses compteurs n\'ont plus de zone.'));
    }

    private function validated(Request $request, ?Zone $zone = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('zones')
                ->where('organisation_id', $request->user()->organisation_id)->ignore($zone)],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
