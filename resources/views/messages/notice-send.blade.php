@if (\App\Support\PhoneNumber::isInternational($customer->phone))
    <form class="inline" method="POST" action="{{ route('notices.send', 'whatsapp') }}" target="_blank" rel="noopener" data-message-send>
        @csrf
        <input type="hidden" name="meter_id" value="{{ $meterId }}">
        <input type="hidden" name="customer_id" value="{{ $customer->id }}">
        <button @class(['btn small', 'ghost' => $sent ?? false])><x-icon name="send" /> {{ __('Avis WhatsApp') }}</button>
    </form>
    <form class="inline touch-only" method="POST" action="{{ route('notices.send', 'sms') }}" data-message-send>
        @csrf
        <input type="hidden" name="meter_id" value="{{ $meterId }}">
        <input type="hidden" name="customer_id" value="{{ $customer->id }}">
        <button class="btn ghost small"><x-icon name="message-square" /> {{ __('Avis SMS') }}</button>
    </form>
@endif
