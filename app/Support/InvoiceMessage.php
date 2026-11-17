<?php

namespace App\Support;

use App\Models\CutoffNotice;
use App\Models\Payment;
use App\Models\Reading;
use App\Models\Receipt;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

// Messages préparés dans l'application de l'utilisateur : c'est lui qui appuie sur Envoyer.
// Dans WhatsApp, *texte* s'affiche en gras. Les SMS restent courts et sans accents :
// un accent peut réduire un SMS à 70 caractères et doubler son coût.
class InvoiceMessage
{
    // Étape 1 : la facture à payer, avec ses délais. Le message ne parle pas du PDF : il n'est joint que si l'expéditeur l'ajoute.
    public static function whatsapp(Reading $reading): string
    {
        $arrears = self::arrears($reading);
        $total = $reading->balance() + $arrears;
        $water = (float) $reading->amount - (float) $reading->maintenance_fee;

        $lines = [
            '💧 *'.__('Facture d\'eau — :v1', ['v1' => period_label($reading->period)]).'*',
            '',
            __('Bonjour :v1 *:v2*,', ['v1' => $reading->customer->title(), 'v2' => $reading->customer->name]),
            __('Voici le détail de votre consommation :'),
            '',
            '• '.__('N° de facture : :v1', ['v1' => $reading->invoiceNumber()]),
            '• '.__('Compteur : :v1', ['v1' => $reading->meter->number]),
            '• '.__('Index : :v1 → :v2', ['v1' => qty($reading->previous_index), 'v2' => qty($reading->current_index)]),
            '• '.__('Consommation : :v1 m³', ['v1' => qty($reading->consumption)]),
            '• '.__('Eau consommée : :v1', ['v1' => money($water)]),
        ];

        if ((float) $reading->maintenance_fee > 0) {
            $lines[] = '• '.__('Frais d\'entretien : :v1', ['v1' => money($reading->maintenance_fee)]);
        }

        if ((float) $reading->paid_amount > 0) {
            $lines[] = '• '.__('Déjà payé : :v1', ['v1' => money($reading->paid_amount)]);
        }

        if ($arrears > 0) {
            $lines[] = '• '.__('Impayés des mois précédents : :v1', ['v1' => money($arrears)]);
        }

        $lines[] = '';

        if ($total > 0) {
            $lines[] = '💰 *'.__('Total à payer : :v1', ['v1' => money($total)]).'*';
        }

        if ($reading->balance() <= 0) {
            $lines[] = '✅ '.__('Cette facture est réglée. Merci.');
        } elseif ($reading->isOverdue()) {
            $lines[] = '⚠️ '.__('Échéance dépassée depuis le *:v1*. Merci de régler dès que possible.', ['v1' => $reading->due_date->format('d/m/Y')]);
        } elseif ($reading->due_date) {
            $lines[] = '📅 '.__('À régler avant le *:v1*.', ['v1' => $reading->due_date->format('d/m/Y')]);
            $lines[] = __('Passé ce délai, un avis de coupure vous sera envoyé, avec :v1 jour(s) pour régler.', ['v1' => auth()->user()->organisation->cutoff_grace_days]);
        }

        array_push($lines, '', __('Merci de votre confiance.'));

        return implode("\n", array_merge($lines, self::signature()));
    }

    public static function sms(Reading $reading): string
    {
        $total = $reading->balance() + self::arrears($reading);

        $text = self::prefix().__('Bonjour :v1, facture :v2, compteur :v3 : :v4 m³.', [
            'v1' => $reading->customer->smsName(),
            'v2' => period_label($reading->period),
            'v3' => $reading->meter->number,
            'v4' => qty($reading->consumption),
        ]);

        if ($total > 0) {
            $text .= ' '.__('Total à payer : :v1', ['v1' => money($total)]);
            $text .= match (true) {
                $reading->isOverdue() => ', '.__('en retard depuis le :v1.', ['v1' => $reading->due_date->format('d/m/Y')]),
                $reading->balance() > 0 && (bool) $reading->due_date => ' '.__('avant le :v1.', ['v1' => $reading->due_date->format('d/m/Y')]),
                default => '.',
            };
        } else {
            $text .= ' '.__('Cette facture est réglée. Merci.');
        }

        return self::finishSms($text);
    }

    // Rappel avant l'échéance ; une fois celle-ci dépassée, c'est l'avis de coupure qui part.
    public static function reminder(Reading $reading): string
    {
        $arrears = self::arrears($reading);

        $lines = [
            '🔔 *'.__('Rappel de paiement').'*',
            '',
            __('Bonjour :v1 *:v2*,', ['v1' => $reading->customer->title(), 'v2' => $reading->customer->name]),
            __('Nous vous rappelons qu\'il reste un montant à payer pour votre eau.'),
            '',
            '• '.__('Facture : :v1', ['v1' => $reading->label()]),
            '• '.__('Compteur : :v1', ['v1' => $reading->meter->number]),
            '• '.__('Reste à payer : :v1', ['v1' => money($reading->balance())]),
        ];

        if ($arrears > 0) {
            $lines[] = '• '.__('Impayés des mois précédents : :v1', ['v1' => money($arrears)]);
        }

        array_push($lines, '', '💰 *'.__('Total à payer : :v1', ['v1' => money($reading->balance() + $arrears)]).'*');

        $lines[] = $reading->due_date
            ? '📅 '.__('Merci de régler avant l\'échéance du *:v1* pour éviter une coupure d\'eau.', ['v1' => $reading->due_date->format('d/m/Y')])
            : __('Merci de régler dès que possible pour éviter une coupure d\'eau.');

        array_push($lines, '', __('Si vous avez déjà payé, ne tenez pas compte de ce message.'));

        return implode("\n", array_merge($lines, self::signature()));
    }

    public static function reminderSms(Reading $reading): string
    {
        $text = self::prefix().__('Rappel pour :v1, compteur :v2 : :v3 a payer', [
            'v1' => $reading->customer->smsName(),
            'v2' => $reading->meter->number,
            'v3' => money($reading->balance() + self::arrears($reading)),
        ]);
        $text .= $reading->due_date ? ' '.__('avant le :v1.', ['v1' => $reading->due_date->format('d/m/Y')]) : '.';

        return self::finishSms($text);
    }

    // Étape 2 : la confirmation après un paiement, sans lien ni document.
    public static function payment(Payment $payment): string
    {
        $reading = $payment->reading;
        $remaining = self::remainingAfter($reading);

        $lines = [
            '✅ *'.__('Confirmation de paiement').'*',
            '',
            __('Bonjour :v1 *:v2*,', ['v1' => $reading->customer->title(), 'v2' => $reading->customer->name]),
            __('Nous confirmons avoir reçu votre paiement de *:v1*.', ['v1' => money($payment->amount)]),
            '',
            '• '.__('Date : :v1', ['v1' => $payment->paid_at->format('d/m/Y')]),
        ];

        if ($payment->method) {
            $lines[] = '• '.__('Mode : :v1', ['v1' => $payment->method]);
        }

        if ($payment->reference) {
            $lines[] = '• '.__('Référence : :v1', ['v1' => $payment->reference]);
        }

        array_push(
            $lines,
            '• '.__('Facture : :v1', ['v1' => $reading->label()]),
            '• '.__('Compteur : :v1', ['v1' => $reading->meter->number]),
            '',
            $remaining > 0
                ? '💰 *'.__('Reste à payer : :v1', ['v1' => money($remaining)]).'*'
                : '🎉 *'.__('Votre compteur est à jour.').'*',
        );

        return implode("\n", array_merge($lines, self::signature()));
    }

    public static function paymentSms(Payment $payment): string
    {
        $reading = $payment->reading;
        $remaining = self::remainingAfter($reading);

        $text = self::prefix().__('Confirmation de paiement : :v1, :v2 recu le :v3, compteur :v4.', [
            'v1' => $reading->customer->smsName(),
            'v2' => money($payment->amount),
            'v3' => $payment->paid_at->format('d/m/Y'),
            'v4' => $reading->meter->number,
        ]);
        $text .= ' '.($remaining > 0 ? __('Reste a payer : :v1.', ['v1' => money($remaining)]) : __('Compteur a jour.'));

        return self::finishSms($text);
    }

    // Étape 3 : mêmes factures et même date limite que l'avis imprimé.
    public static function notice(CutoffNotice $notice): string
    {
        $readings = $notice->overdueReadings()->get();

        $lines = [
            '⚠️ *'.__('AVIS DE COUPURE D\'EAU').'*',
            __('N° :v1', ['v1' => $notice->number()]),
            '',
            __('Bonjour :v1 *:v2*,', ['v1' => $notice->customer->title(), 'v2' => $notice->customer->name]),
            __('L\'échéance de paiement est dépassée pour le compteur :v1 :', ['v1' => $notice->meter->number]),
            '',
        ];

        foreach ($readings as $reading) {
            $lines[] = '• '.$reading->label().' : '.money($reading->balance());
        }

        array_push($lines, '', '💰 *'.__('Total à payer : :v1', ['v1' => money($readings->sum(fn ($reading) => $reading->balance()))]).'*');

        $lines[] = $notice->deadline->lt(today())
            ? '🚫 '.__('La date limite du *:v1* est dépassée : l\'alimentation en eau peut être coupée à tout moment. Merci de régler immédiatement.', ['v1' => $notice->deadline->format('d/m/Y')])
            : '📅 '.__('Sans paiement avant le *:v1*, l\'alimentation en eau sera coupée.', ['v1' => $notice->deadline->format('d/m/Y')]);

        array_push($lines, '', __('Si vous avez déjà payé, ne tenez pas compte de ce message.'));

        return implode("\n", array_merge($lines, self::signature()));
    }

    public static function noticeSms(CutoffNotice $notice): string
    {
        $text = self::prefix().__('AVIS DE COUPURE, :v1, compteur :v2 : :v3 a payer', [
            'v1' => $notice->customer->smsName(),
            'v2' => $notice->meter->number,
            'v3' => money($notice->overdueReadings()->get()->sum(fn ($reading) => $reading->balance())),
        ]);
        $text .= $notice->deadline->lt(today())
            ? ', '.__('date limite depassee, coupure possible a tout moment.')
            : ' '.__('avant le :v1, sinon coupure.', ['v1' => $notice->deadline->format('d/m/Y')]);

        return self::finishSms($text);
    }

    public static function whatsappLink(Reading $reading): string
    {
        return self::whatsappUrl($reading->customer->phone, self::whatsapp($reading));
    }

    public static function smsLink(Reading $reading): string
    {
        return self::smsUrl($reading->customer->phone, self::sms($reading));
    }

    public static function reminderLink(Reading $reading, string $channel): string
    {
        return $channel === 'sms'
            ? self::smsUrl($reading->customer->phone, self::reminderSms($reading))
            : self::whatsappUrl($reading->customer->phone, self::reminder($reading));
    }

    // Le reçu d'un encaissement de plusieurs mois : chaque compteur reste séparé, avec ses mois et son reste dû.
    public static function receipt(Receipt $receipt): string
    {
        $customer = $receipt->customer;
        $lines = [
            '✅ *'.__('Reçu d\'encaissement').'*',
            __('N° :v1', ['v1' => $receipt->number()]),
            '',
            __('Bonjour :v1 *:v2*,', ['v1' => $customer->title(), 'v2' => $customer->name]),
            __('Nous confirmons avoir reçu votre paiement de *:v1*.', ['v1' => money($receipt->total)]),
            '',
            '• '.__('Date : :v1', ['v1' => $receipt->paid_at->format('d/m/Y')]),
        ];

        if ($receipt->method) {
            $lines[] = '• '.__('Mode : :v1', ['v1' => $receipt->method]);
        }

        if ($receipt->reference) {
            $lines[] = '• '.__('Référence : :v1', ['v1' => $receipt->reference]);
        }

        $remaining = 0;
        foreach ($receipt->byMeter() as $group) {
            $lines[] = '';
            $lines[] = '*'.__('Compteur :v1', ['v1' => $group->meter->number]).'*';
            foreach ($group->payments as $payment) {
                $lines[] = '• '.$payment->reading->label().' : '.money($payment->amount)
                    .($payment->reading->balance() > 0 ? ' ('.__('reste :v1', ['v1' => money($payment->reading->balance())]).')' : ' ('.__('payé').')');
            }
            $lines[] = __('Sous-total : :v1', ['v1' => money($group->subtotal)]);
            $lines[] = $group->remaining > 0
                ? __('Reste dû sur ce compteur : :v1', ['v1' => money($group->remaining)])
                : __('Ce compteur est à jour.');
            $remaining += $group->remaining;
        }

        array_push(
            $lines,
            '',
            '💵 *'.__('Total encaissé : :v1', ['v1' => money($receipt->total)]).'*',
            $remaining > 0
                ? '💰 *'.__('Reste à payer : :v1', ['v1' => money($remaining)]).'*'
                : '🎉 *'.__('Vos compteurs concernés sont à jour.').'*',
        );

        return implode("\n", array_merge($lines, self::signature()));
    }

    public static function receiptSms(Receipt $receipt): string
    {
        $groups = $receipt->byMeter();
        $remaining = $groups->sum('remaining');

        $text = self::prefix().__('Recu :v1 : :v2 recu le :v3 de :v4, :v5 mois.', [
            'v1' => $receipt->number(),
            'v2' => money($receipt->total),
            'v3' => $receipt->paid_at->format('d/m/Y'),
            'v4' => $receipt->customer->smsName(),
            'v5' => $receipt->payments->count(),
        ]);
        $text .= ' '.($remaining > 0 ? __('Reste a payer : :v1.', ['v1' => money($remaining)]) : __('Compteurs a jour.'));

        return self::finishSms($text);
    }

    public static function receiptLink(Receipt $receipt, string $channel): string
    {
        return $channel === 'sms'
            ? self::smsUrl($receipt->customer->phone, self::receiptSms($receipt))
            : self::whatsappUrl($receipt->customer->phone, self::receipt($receipt));
    }

    public static function paymentLink(Payment $payment, string $channel): string
    {
        $phone = $payment->reading->customer->phone;

        return $channel === 'sms'
            ? self::smsUrl($phone, self::paymentSms($payment))
            : self::whatsappUrl($phone, self::payment($payment));
    }

    public static function noticeLink(CutoffNotice $notice, string $channel): string
    {
        return $channel === 'sms'
            ? self::smsUrl($notice->customer->phone, self::noticeSms($notice))
            : self::whatsappUrl($notice->customer->phone, self::notice($notice));
    }

    // Depuis le navigateur, on redirige directement vers WhatsApp ou les SMS. Depuis l'application installée,
    // Android bloque l'ouverture d'une autre application après une redirection : on affiche une page avec
    // un bouton à toucher, qui ouvre le message.
    public static function open(string $url): Response
    {
        if (request()->input('via') !== 'app') {
            return redirect()->away($url);
        }

        return response()->view('messages.open', [
            'url' => $url,
            'launch' => self::launchUrl($url, (string) request()->userAgent()),
            'channel' => str_starts_with($url, 'sms:') ? 'sms' : 'whatsapp',
            'back' => url()->previous(),
        ]);
    }

    // Sur Android, l'adresse « intent » ouvre WhatsApp ou WhatsApp Business (au choix s'il y a les deux),
    // et revient au lien wa.me si aucun n'est installé.
    public static function launchUrl(string $url, string $userAgent): string
    {
        if (! str_contains($userAgent, 'Android') || ! preg_match('#^https://wa\.me/(\d+)\?text=(.*)$#s', $url, $parts)) {
            return $url;
        }

        return 'intent://send/?phone='.$parts[1].'&text='.$parts[2]
            .'#Intent;scheme=whatsapp;S.browser_fallback_url='.rawurlencode($url).';end';
    }

    private static function whatsappUrl(string $phone, string $message): string
    {
        return 'https://wa.me/'.ltrim($phone, '+').'?text='.rawurlencode($message);
    }

    // « ?& » : la forme acceptée à la fois par Android et par iPhone.
    private static function smsUrl(string $phone, string $message): string
    {
        return 'sms:'.$phone.'?&body='.rawurlencode($message);
    }

    // Impayés des mois précédents, calculés comme sur la facture imprimée.
    private static function arrears(Reading $reading): float
    {
        return (float) Reading::unpaid()
            ->where('period', '<', $reading->period)
            ->where('meter_id', $reading->meter_id)
            ->where('customer_id', $reading->customer_id)
            ->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as total')
            ->value('total');
    }

    private static function remainingAfter(Reading $reading): float
    {
        return $reading->balance() + (float) Reading::unpaid()
            ->where('id', '<>', $reading->id)
            ->where('meter_id', $reading->meter_id)
            ->where('customer_id', $reading->customer_id)
            ->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as total')
            ->value('total');
    }

    private static function signature(): array
    {
        $organisation = auth()->user()->organisation;

        return array_filter(
            ['', '*'.$organisation->name.'*', $organisation->phone ? '📞 '.$organisation->phone : null],
            fn ($line) => $line !== null,
        );
    }

    private static function prefix(): string
    {
        return auth()->user()->organisation->name.' - ';
    }

    private static function finishSms(string $text): string
    {
        $phone = auth()->user()->organisation->phone;

        return Str::ascii($phone ? $text.' '.__('Tél. : :v1', ['v1' => $phone]) : $text);
    }
}
