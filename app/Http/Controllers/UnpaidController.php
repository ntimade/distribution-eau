<?php

namespace App\Http\Controllers;

use App\Models\Reading;
use App\Support\SortedTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UnpaidController extends Controller
{
    public function index(Request $request): View
    {
        $months = SortedTable::query(Reading::selectRaw('period, COUNT(*) as readings_count, SUM(amount) as billed, SUM(paid_amount) as paid, SUM(amount - paid_amount) as unpaid')
            ->groupBy('period')
            ->orderByDesc('period'), 'mois', [
                'period' => 'period',
                'readings' => 'readings_count',
                'billed' => 'billed',
                'paid' => 'paid',
                'unpaid' => 'unpaid',
            ], tieBreak: false);

        $period = (string) $request->query('period');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            $period = Reading::unpaid()->max('period') ?? now()->format('Y-m');
        }

        return view('unpaid.index', [
            'months' => $months,
            'period' => $period,
            'readings' => SortedTable::query(Reading::with(['meter', 'customer'])->where('period', $period)->unpaid()
                ->orderByRaw('amount - paid_amount DESC'), 'detail', [
                    'client' => SortedTable::related('customers', 'name', 'id', 'readings.customer_id'),
                    'phone' => SortedTable::related('customers', 'phone', 'id', 'readings.customer_id'),
                    'meter' => SortedTable::related('meters', 'number', 'id', 'readings.meter_id'),
                    'amount' => 'amount',
                    'paid' => 'paid_amount',
                    'rest' => DB::raw('amount - paid_amount'),
                    'due' => 'due_date',
                ]),
            'periodUnpaid' => Reading::where('period', $period)->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as total')->value('total'),
            'totalUnpaid' => Reading::selectRaw('COALESCE(SUM(amount - paid_amount), 0) as total')->value('total'),
        ]);
    }
}
