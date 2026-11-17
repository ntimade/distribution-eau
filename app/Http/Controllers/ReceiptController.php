<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Meter;
use App\Models\Reading;
use App\Models\Receipt;
use App\Services\BillingService;
use App\Support\InvoiceMessage;
use App\Support\PhoneNumber;
use App\Support\SortedTable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Encaissement de plusieurs mois en une fois. Le client choisit librement les mois à régler, sans suivre
 * l'ordre, sur un ou plusieurs de ses compteurs ; chaque compteur reste séparé. Le total se calcule seul,
 * chaque mois passe à « payé » (ou garde son reste après un acompte) et un reçu récapitule l'encaissement.
 */
class ReceiptController extends Controller
{
    private const RELATIONS = ['customer', 'user', 'payments.reading.meter'];

    public function __construct(private BillingService $billing)
    {
    }

    // L'historique des encaissements, filtrable par client et par période.
    public function index(Request $request): View
    {
        $query = Receipt::query()
            ->when(ctype_digit((string) $request->query('customer_id')) ? (int) $request->query('customer_id') : null, fn (Builder $q, int $id) => $q->where('customer_id', $id))
            ->when($this->date($request, 'from'), fn (Builder $q, string $date) => $q->whereDate('paid_at', '>=', $date))
            ->when($this->date($request, 'to'), fn (Builder $q, string $date) => $q->whereDate('paid_at', '<=', $date));

        return view('receipts.index', [
            'total' => (float) (clone $query)->sum('total'),
            'count' => (clone $query)->count(),
            'receipts' => SortedTable::query((clone $query)->with(['customer', 'payments.reading.meter'])->latest('paid_at')->latest('id'), 'encaissements', [
                'date' => 'paid_at',
                'client' => SortedTable::related('customers', 'name', 'id', 'receipts.customer_id'),
                'amount' => 'total',
            ]),
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'phone']),
        ]);
    }

    /**
     * Sans client : on le choisit. Avec un client : ses mois impayés, compteur par compteur.
     * « meter » restreint l'affichage à un compteur (bouton de la fiche du compteur).
     */
    public function create(Request $request): View
    {
        $customer = ctype_digit((string) $request->query('customer')) ? Customer::find((int) $request->query('customer')) : null;
        $meter = $customer && ctype_digit((string) $request->query('meter'))
            ? Meter::where('customer_id', $customer->id)->find((int) $request->query('meter'))
            : null;

        $groups = $customer ? $this->debtsByMeter($customer, $meter) : collect();

        return view('receipts.create', [
            'customer' => $customer,
            'meter' => $meter,
            'groups' => $groups,
            'customers' => $customer ? collect() : $this->customersWithDebts(),
            // Plusieurs compteurs endettés : on choisit d'abord le compteur (ou tous, sur demande).
            'chooseMeter' => $customer && ! $meter && $groups->count() > 1 && ! $request->boolean('tous'),
        ]);
    }

    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'meter_id' => ['nullable', 'integer'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:100'],
        ], [
            'amount.required' => __('Saisissez le montant versé.'),
            'amount.gt' => __('Saisissez le montant versé.'),
        ], ['amount' => __('montant versé')]);

        // Depuis la fiche d'un compteur, seule la dette de ce compteur (du client) est réglée.
        $meterId = ! empty($data['meter_id'])
            ? Reading::where('customer_id', $customer->id)->where('meter_id', $data['meter_id'])->value('meter_id')
            : null;

        // Le montant versé paie d'abord la dette la plus ancienne (règlement complet ou avance).
        $lines = $this->billing->allocateOldestFirst($customer, $meterId, (float) $data['amount']);

        $receipt = $this->billing->recordReceipt($customer, $lines, $data['paid_at'], $data['method'] ?? null, $data['reference'] ?? null);

        return redirect()->route('receipts.show', $receipt)->with('success', __('Encaissement enregistré : :v1.', ['v1' => money($receipt->total)]));
    }

    public function show(Receipt $receipt): View
    {
        $receipt->load(self::RELATIONS);

        return view('receipts.show', ['receipt' => $receipt, 'groups' => $receipt->byMeter()]);
    }

    // Le reçu à imprimer et à remettre au client.
    public function print(Request $request, Receipt $receipt): View
    {
        $receipt->load(self::RELATIONS);

        return view('receipts.print', [
            'receipt' => $receipt,
            'groups' => $receipt->byMeter(),
            'organisation' => $request->user()->organisation,
            'logoSrc' => route('settings.logo', ['v' => $request->user()->organisation->updated_at?->timestamp]),
            'backUrl' => route('receipts.show', $receipt),
        ]);
    }

    public function pdf(Request $request, Receipt $receipt): HttpResponse
    {
        $receipt->load(self::RELATIONS);
        $organisation = $request->user()->organisation;

        $pdf = new Dompdf(new Options(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false]));
        $pdf->loadHtml(view('receipts.pdf', [
            'receipt' => $receipt,
            'groups' => $receipt->byMeter(),
            'organisation' => $organisation,
            'logoSrc' => $organisation->logoDataUri(),
        ])->render());
        $pdf->setPaper('A4');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.Str::slug(__('Reçu').' '.$receipt->number()).'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    // Annule tout l'encaissement : chaque mois retrouve ce qu'il restait à payer avant.
    public function destroy(Receipt $receipt): RedirectResponse
    {
        $customerId = $receipt->customer_id;
        $this->billing->cancelReceipt($receipt);

        return redirect()->route('customers.show', $customerId)->with('success', __('Encaissement annulé : les mois concernés sont de nouveau à payer.'));
    }

    // On note l'ouverture de l'application : on ne peut pas savoir si le message est parti.
    public function message(Receipt $receipt, string $channel): Response
    {
        $receipt->load(self::RELATIONS);

        if (! PhoneNumber::isInternational($receipt->customer->phone)) {
            return back()->with('error', __('Le numéro de ce client est absent ou à corriger.'));
        }

        $receipt->forceFill([$channel.'_opened_at' => now()])->save();

        return InvoiceMessage::open(InvoiceMessage::receiptLink($receipt, $channel));
    }

    // Les mois impayés du client, compteur par compteur, du plus ancien au plus récent.
    private function debtsByMeter(Customer $customer, ?Meter $meter): Collection
    {
        return Reading::with('meter.zone')->unpaid()
            ->where('customer_id', $customer->id)
            ->when($meter, fn (Builder $query) => $query->where('meter_id', $meter->id))
            ->orderBy('period')->orderBy('id')->get()
            ->groupBy('meter_id')
            ->map(fn (Collection $readings) => (object) [
                'meter' => $readings->first()->meter,
                'readings' => $readings->values(),
                'total' => round((float) $readings->sum(fn (Reading $reading) => $reading->balance()), 3),
            ])
            ->sortBy(fn ($group) => $group->meter->number)
            ->values();
    }

    private function customersWithDebts(): Collection
    {
        return Customer::query()
            ->whereHas('readings', fn (Builder $query) => $query->unpaid())
            ->addSelect(['debt' => Reading::selectRaw('COALESCE(SUM(amount - paid_amount), 0)')->whereColumn('readings.customer_id', 'customers.id')->whereColumn('paid_amount', '<', 'amount')])
            ->withCount(['readings as unpaid_months' => fn (Builder $query) => $query->unpaid()])
            ->orderBy('name')->get();
    }

    private function date(Request $request, string $key): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query($key)) ? $request->query($key) : null;
    }
}
