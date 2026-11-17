<form class="inline" method="POST" action="{{ route('messages.open', [$reading, 'whatsapp']) }}" target="_blank" rel="noopener" data-message-send data-pdf-url="{{ route('invoices.pdf', $reading) }}">
    @csrf
    <button @class(['btn', 'small' => $small ?? false, 'ghost' => $reading->whatsapp_opened_at])><x-icon name="send" /> {{ ($small ?? false) ? __('WhatsApp') : __('Envoyer sur WhatsApp') }}</button>
</form>
<a @class(['btn ghost', 'small' => $small ?? false]) href="{{ route('invoices.pdf', $reading) }}" download data-pdf-share title="{{ __('Partager ou télécharger la facture en PDF') }}"><x-icon name="receipt" /> {{ __('PDF') }}</a>
{{-- Pas de cible _blank : le lien sms: ouvre l'application de messages sans laisser d'onglet vide. --}}
<form class="inline touch-only" method="POST" action="{{ route('messages.open', [$reading, 'sms']) }}" data-message-send>
    @csrf
    <button @class(['btn', 'small' => $small ?? false, 'ghost' => $reading->sms_opened_at || ! $reading->whatsapp_opened_at])><x-icon name="message-square" /> {{ ($small ?? false) ? __('SMS') : __('Envoyer par SMS') }}</button>
</form>
