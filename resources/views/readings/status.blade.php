@if ((float) $reading->amount === 0.0 || $reading->balance() <= 0)
    <span class="badge ok">{{ __('Payé') }}</span>
@elseif ((float) $reading->paid_amount > 0)
    <span class="badge partial">{{ __('Partiel') }}</span>
@else
    <span class="badge ko">{{ __('Impayé') }}</span>
@endif
