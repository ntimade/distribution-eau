@if (\App\Support\PhoneNumber::isInternational($reading->customer->phone))
    <form class="inline" method="POST" action="{{ route('messages.reminder', [$reading, 'whatsapp']) }}" target="_blank" rel="noopener" data-message-send>
        @csrf
        <button @class(['btn small', 'ghost' => $reading->reminder_opened_at])><x-icon name="send" /> {{ __('Rappel WhatsApp') }}</button>
    </form>
    <form class="inline touch-only" method="POST" action="{{ route('messages.reminder', [$reading, 'sms']) }}" data-message-send>
        @csrf
        <button class="btn ghost small"><x-icon name="message-square" /> {{ __('Rappel SMS') }}</button>
    </form>
@endif
