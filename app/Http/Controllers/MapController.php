<?php

namespace App\Http\Controllers;

use App\Models\Meter;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MapController extends Controller
{
    public function index(Request $request): View
    {
        $zoneId = $request->query('zone_id');

        $points = Meter::with(['customer', 'zone'])
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->when($zoneId, fn ($q) => $q->where('zone_id', $zoneId))
            ->withPaymentStatus()
            ->withExists('replacement')
            ->get()
            // Un compteur remplacé et soldé n'existe plus sur le terrain.
            ->reject(fn (Meter $meter) => $meter->replacement_exists && (float) $meter->unpaid <= 0)
            ->values()
            ->map(fn (Meter $meter) => [
                'lat' => (float) $meter->latitude,
                'lng' => (float) $meter->longitude,
                'number' => $meter->number,
                'customer' => $meter->customer?->name,
                'phone' => $meter->customer?->phone,
                'zone' => $meter->zone?->name,
                'status' => $meter->paymentStatus(),
                'unpaid' => money($meter->unpaid),
                'unpaid_months' => (int) $meter->unpaid_months,
                'url' => route('meters.show', $meter),
            ]);

        return view('map.index', [
            'points' => $points,
            'zones' => Zone::orderBy('name')->get(),
            'zoneId' => $zoneId,
            'withoutPosition' => Meter::where(fn ($q) => $q->whereNull('latitude')->orWhereNull('longitude'))
                ->when($zoneId, fn ($q) => $q->where('zone_id', $zoneId))->count(),
        ]);
    }
}
