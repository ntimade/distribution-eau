<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Reading;
use App\Services\BillingService;
use App\Support\SortedTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(private BillingService $billing)
    {
    }

    public function index(Request $request): View
    {
        $query = Payment::query()
            ->when($request->query('customer_id'), fn ($q, $id) => $q->whereHas('reading', fn ($r) => $r->where('customer_id', $id)))
            ->when($request->query('from'), fn ($q, $date) => $q->whereDate('paid_at', '>=', $date))
            ->when($request->query('to'), fn ($q, $date) => $q->whereDate('paid_at', '<=', $date));

        return view('payments.index', [
            'total' => (clone $query)->sum('amount'),
            'payments' => SortedTable::query($query->with(['reading.customer', 'reading.meter', 'receipt'])->latest('paid_at')->latest('id'), 'paiements', [
                'date' => 'paid_at',
                'client' => DB::table('readings')->join('customers', 'customers.id', '=', 'readings.customer_id')
                    ->select('customers.name')->whereColumn('readings.id', 'payments.reading_id')->limit(1),
                'meter' => DB::table('readings')->join('meters', 'meters.id', '=', 'readings.meter_id')
                    ->select('meters.number')->whereColumn('readings.id', 'payments.reading_id')->limit(1),
                'period' => SortedTable::related('readings', 'period', 'id', 'payments.reading_id'),
                'method' => 'method',
                'reference' => 'reference',
                'amount' => 'amount',
            ]),
            'customers' => Customer::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Reading $reading): RedirectResponse
    {
        $data = $this->validated($request);

        $payment = $this->billing->recordPayment($reading, (float) $data['amount'], $data['paid_at'], $data['method'] ?? null, $data['reference'] ?? null);

        return redirect()->route('readings.show', $reading)->with('success', __('Paiement enregistré.'))->with('notify_payment', $payment->id);
    }

    public function edit(Payment $payment): View
    {
        return view('payments.edit', ['payment' => $payment->load('reading.customer', 'reading.meter')]);
    }

    public function update(Request $request, Payment $payment): RedirectResponse
    {
        $data = $this->validated($request);

        $this->billing->updatePayment($payment, (float) $data['amount'], $data['paid_at'], $data['method'] ?? null, $data['reference'] ?? null);

        return redirect()->route('readings.show', $payment->reading_id)->with('success', __('Paiement corrigé.'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        $this->billing->cancelPayment($payment);

        return back()->with('success', __('Paiement annulé.'));
    }
}
