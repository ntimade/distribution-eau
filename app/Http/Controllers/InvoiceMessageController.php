<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Reading;
use App\Services\CutoffNoticeService;
use App\Support\InvoiceMessage;
use App\Support\PhoneNumber;
use App\Support\SortedTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class InvoiceMessageController extends Controller
{
    // Un paiement plus ancien n'attend plus de confirmation.
    public const PAYMENT_DAYS = 30;

    private const STATES = ['a-envoyer', 'paiements', 'avis', 'ouverts', 'sans-numero'];

    public function index(Request $request, CutoffNoticeService $notices): View
    {
        $period = (string) $request->query('period');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            $period = Reading::real()->max('period') ?? now()->format('Y-m');
        }

        $state = in_array($request->query('etat'), self::STATES, true) ? $request->query('etat') : self::STATES[0];
        $noticeRows = $notices->toSend();

        return view('messages.index', [
            'period' => $period,
            'state' => $state,
            'counts' => [
                'a-envoyer' => $this->invoicesInState($period, 'a-envoyer')->count(),
                'paiements' => self::paymentsToConfirm()->count(),
                'avis' => $noticeRows->count(),
                'ouverts' => $this->invoicesInState($period, 'ouverts')->count(),
                'sans-numero' => $this->invoicesInState($period, 'sans-numero')->count(),
            ],
            'readings' => in_array($state, ['a-envoyer', 'ouverts', 'sans-numero'], true)
                ? SortedTable::query($this->invoicesInState($period, $state)->with(['customer', 'meter'])->orderBy('id'), 'envois', [
                    'client' => SortedTable::related('customers', 'name', 'id', 'readings.customer_id'),
                    'phone' => SortedTable::related('customers', 'phone', 'id', 'readings.customer_id'),
                    'meter' => SortedTable::related('meters', 'number', 'id', 'readings.meter_id'),
                    'amount' => 'amount',
                    'due' => 'due_date',
                    'opened' => DB::raw('COALESCE(whatsapp_opened_at, sms_opened_at)'),
                ])
                : null,
            'payments' => $state === 'paiements'
                ? SortedTable::query(self::paymentsToConfirm()->with(['reading.customer', 'reading.meter'])->latest('paid_at')->latest('id'), 'confirmation', [
                    'date' => 'paid_at',
                    'amount' => 'amount',
                ])
                : null,
            'notices' => $state === 'avis'
                ? SortedTable::collection($noticeRows, 'avis', [
                    'client' => fn ($row) => mb_strtolower($row->customer->name),
                    'meter' => fn ($row) => $row->meter->number,
                    'total' => fn ($row) => $row->total,
                    'due' => fn ($row) => $row->oldest_due,
                ])
                : null,
        ]);
    }

    private function invoicesInState(string $period, string $state): Builder
    {
        $reachable = fn (Builder $customer) => $customer->where('phone', 'like', '+%');
        $query = Reading::real()->where('period', $period);

        return match ($state) {
            'ouverts' => $query->whereHas('customer', $reachable)
                ->where(fn ($opened) => $opened->whereNotNull('whatsapp_opened_at')->orWhereNotNull('sms_opened_at')),
            'sans-numero' => $query->whereDoesntHave('customer', $reachable),
            // Une facture échue passe à l'étape des avis de coupure.
            default => self::invoicesToSend($query),
        };
    }

    private static function invoicesToSend(Builder $query): Builder
    {
        return $query->unpaid()
            ->where(fn ($due) => $due->whereNull('due_date')->orWhere('due_date', '>=', now()->toDateString()))
            ->whereHas('customer', fn (Builder $customer) => $customer->where('phone', 'like', '+%'))
            ->whereNull('whatsapp_opened_at')->whereNull('sms_opened_at');
    }

    private static function paymentsToConfirm(): Builder
    {
        return Payment::query()
            ->whereNull('whatsapp_opened_at')->whereNull('sms_opened_at')
            ->where('created_at', '>=', now()->subDays(self::PAYMENT_DAYS))
            ->whereHas('reading.customer', fn (Builder $customer) => $customer->where('phone', 'like', '+%'));
    }

    // Le nombre affiché dans le menu : tout ce qui attend un envoi, tous mois confondus.
    public static function waitingCount(CutoffNoticeService $notices): int
    {
        return self::invoicesToSend(Reading::real())->count()
            + self::paymentsToConfirm()->count()
            + $notices->toSend()->count();
    }

    public function openReminder(Reading $reading, string $channel): Response
    {
        if ($reading->balance() <= 0) {
            return back()->with('error', __('Cette facture est déjà réglée.'));
        }

        if ($reading->isOverdue()) {
            return back()->with('error', __('L\'échéance est dépassée : envoyez plutôt un avis de coupure.'));
        }

        if (! PhoneNumber::isInternational($reading->customer->phone)) {
            return back()->with('error', __('Le numéro de ce client est absent ou à corriger.'));
        }

        $reading->forceFill(['reminder_opened_at' => now()])->save();

        return InvoiceMessage::open(InvoiceMessage::reminderLink($reading, $channel));
    }

    public function openPayment(Payment $payment, string $channel): Response
    {
        if (! PhoneNumber::isInternational($payment->reading->customer->phone)) {
            return back()->with('error', __('Le numéro de ce client est absent ou à corriger.'));
        }

        $payment->forceFill([$channel.'_opened_at' => now()])->save();

        return InvoiceMessage::open(InvoiceMessage::paymentLink($payment, $channel));
    }

    // On note l'ouverture de l'application : on ne peut pas savoir si le message est parti.
    public function open(Reading $reading, string $channel): Response
    {
        abort_if($reading->is_opening, 404);

        if (! PhoneNumber::isInternational($reading->customer->phone)) {
            return back()->with('error', __('Le numéro de ce client est absent ou à corriger.'));
        }

        $reading->forceFill([$channel.'_opened_at' => now()])->save();

        return InvoiceMessage::open($channel === 'sms' ? InvoiceMessage::smsLink($reading) : InvoiceMessage::whatsappLink($reading));
    }
}
