<?php

namespace App\Http\Controllers;

use App\Models\CutoffNotice;
use App\Services\CutoffNoticeService;
use App\Support\InvoiceMessage;
use App\Support\PhoneNumber;
use App\Support\SortedTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CutoffNoticeController extends Controller
{
    public function __construct(private CutoffNoticeService $notices)
    {
    }

    public function index(): View
    {
        $rows = $this->notices->rows();

        return view('notices.index', [
            'rows' => SortedTable::collection($rows, 'avis', [
                'client' => fn ($row) => mb_strtolower($row->customer->name),
                'phone' => fn ($row) => (string) $row->customer->phone,
                'meter' => fn ($row) => $row->meter->number,
                'invoices' => fn ($row) => $row->invoices,
                'total' => fn ($row) => $row->total,
                'due' => fn ($row) => $row->oldest_due,
                'status' => fn ($row) => $row->status,
            ]),
            'withoutNotice' => $rows->where('status', 'none')->count(),
            'withNotice' => $rows->where('status', '!=', 'none')->count(),
            'toCut' => $rows->where('status', 'cut')->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $row = $this->notices->rows()->first(fn ($row) => $row->meter->id === (int) $request->input('meter_id')
            && $row->customer->id === (int) $request->input('customer_id'));

        if (! $row) {
            return back()->with('error', __('Ce compteur n\'a pas de facture en retard.'));
        }

        return redirect()->route('notices.show', $this->notices->issue(collect([$row]))->first());
    }

    // Crée l'avis s'il n'existe pas encore, puis prépare le message : la date limite reste celle de l'avis.
    public function send(Request $request, string $channel): Response
    {
        $row = $this->notices->rows()->first(fn ($row) => $row->meter->id === (int) $request->input('meter_id')
            && $row->customer->id === (int) $request->input('customer_id'));

        if (! $row) {
            return back()->with('error', __('Ce compteur n\'a pas de facture en retard.'));
        }

        if (! PhoneNumber::isInternational($row->customer->phone)) {
            return back()->with('error', __('Le numéro de ce client est absent ou à corriger.'));
        }

        $notice = $this->notices->issue(collect([$row]))->first();
        $notice->forceFill(['message_opened_at' => now()])->save();

        return InvoiceMessage::open(InvoiceMessage::noticeLink($notice->load(['meter', 'customer']), $channel));
    }

    public function storeAll(): RedirectResponse
    {
        $rows = $this->notices->rows();

        if ($rows->isEmpty()) {
            return back()->with('error', __('Aucune facture en retard.'));
        }

        $this->notices->issue($rows);

        return redirect()->route('notices.print');
    }

    public function show(CutoffNotice $notice): View|RedirectResponse
    {
        if (! $notice->overdueReadings()->exists()) {
            return redirect()->route('notices.index')->with('success', __('Les factures de cet avis ne sont plus en retard.'));
        }

        return $this->render(collect([$notice->load(['meter', 'customer'])]), __('Avis ').$notice->number());
    }

    public function printAll(): View|RedirectResponse
    {
        $notices = CutoffNotice::with(['meter', 'customer'])
            ->whereKey($this->notices->rows()->pluck('notice.id')->filter())
            ->get()
            ->sortBy(fn ($notice) => mb_strtolower($notice->customer->name))
            ->values();

        if ($notices->isEmpty()) {
            return redirect()->route('notices.index')->with('error', __('Aucun avis à imprimer.'));
        }

        return $this->render($notices, __('Avis de coupure'));
    }

    private function render(Collection $notices, string $title): View
    {
        return view('notices.print', [
            'notices' => $notices,
            'title' => $title,
            'backUrl' => route('notices.index'),
        ]);
    }

}
