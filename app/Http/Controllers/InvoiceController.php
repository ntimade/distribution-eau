<?php

namespace App\Http\Controllers;

use App\Models\Reading;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function show(Reading $reading): View
    {
        return $this->render(collect([$reading->load(['meter.zone', 'customer', 'payments.receipt'])]), __('Facture ').$reading->invoiceNumber(), route('readings.show', $reading));
    }

    // La facture en fichier, pour la joindre à un message.
    public function pdf(Reading $reading): Response
    {
        $reading->load(['meter.zone', 'customer', 'payments.receipt']);

        $pdf = new Dompdf(new Options(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false]));
        $pdf->loadHtml(view('invoices.pdf', $this->data(collect([$reading]), __('Facture ').$reading->invoiceNumber(), null) + [
            'organisation' => auth()->user()->organisation,
        ])->render());
        $pdf->setPaper('A4');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.Str::slug(__('Facture').' '.$reading->invoiceNumber()).'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function month(Request $request): View|RedirectResponse
    {
        $period = (string) $request->query('period');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            $period = now()->format('Y-m');
        }

        $readings = Reading::real()->with(['meter.zone', 'customer', 'payments.receipt'])->where('period', $period)->get()
            ->sortBy(fn (Reading $reading) => mb_strtolower($reading->customer->name))->values();

        if ($readings->isEmpty()) {
            return redirect()->route('readings.index', compact('period'))->with('error', __('Aucune facture à imprimer pour ').period_label($period).'.');
        }

        return $this->render($readings, __('Factures ').of_period($period), route('readings.index', compact('period')));
    }

    private function render(Collection $readings, string $title, string $backUrl): View
    {
        return view('invoices.print', $this->data($readings, $title, $backUrl) + ['organisation' => auth()->user()->organisation]);
    }

    private function data(Collection $readings, string $title, ?string $backUrl): array
    {
        // Impayés des mois précédents, par compteur et par client : additionner les factures
        // d'un client à plusieurs compteurs donne sa dette exacte, sans rien compter deux fois.
        $arrears = Reading::unpaid()
            ->where('period', '<', $readings->first()->period)
            ->whereIn('meter_id', $readings->pluck('meter_id'))
            ->selectRaw('meter_id, customer_id, SUM(amount - paid_amount) as total')
            ->groupBy('meter_id', 'customer_id')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->meter_id.'-'.$row->customer_id => $row->total]);

        return compact('readings', 'title', 'backUrl', 'arrears');
    }
}
