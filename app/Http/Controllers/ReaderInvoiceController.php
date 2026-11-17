<?php

namespace App\Http\Controllers;

use App\Models\Reading;
use App\Support\SortedTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

// Les factures impayées de la zone du releveur, à envoyer par WhatsApp ou SMS.
class ReaderInvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $query = Reading::real()->unpaid()->accessibleBy($request->user())->with(['customer', 'meter'])
            ->when($search !== '', fn ($readings) => $readings->where(fn ($match) => $match
                ->whereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%"))
                ->orWhereHas('meter', fn ($meter) => $meter->where('number', 'like', "%{$search}%"))))
            ->orderBy('due_date')->orderBy('id');

        return view('reader-invoices.index', [
            'search' => $search,
            'readings' => SortedTable::query($query, 'factures', [
                'client' => SortedTable::related('customers', 'name', 'id', 'readings.customer_id'),
                'meter' => SortedTable::related('meters', 'number', 'id', 'readings.meter_id'),
                'rest' => DB::raw('amount - paid_amount'),
                'due' => 'due_date',
            ]),
        ]);
    }
}
