@props(['ring'])

@php
    // pathLength = 100 : la longueur d'un arc se lit directement en pourcentage.
    $radius = 58;
    $gap = 0.8;
    $visible = collect($ring['segments'])->filter(fn ($segment) => $segment['share'] > 0)->values();
    $offset = 0;
    $statusIcons = ['success' => 'circle-check', 'warning' => 'circle-alert', 'danger' => 'circle-alert'];
    $statusBadges = ['success' => 'ok', 'warning' => 'partial', 'danger' => 'ko'];
@endphp

<div class="card ring-card">
    <div class="ring-head">
        <h2>{{ $ring['title'] }}</h2>
        @if ($ring['status'])
            <span class="badge ring-status {{ $statusBadges[$ring['status']['level']] }}"><x-icon :name="$statusIcons[$ring['status']['level']]" /> {{ $ring['status']['label'] }}</span>
        @endif
    </div>
    <div class="ring">
        <svg viewBox="0 0 148 148" role="img" aria-label="{{ $ring['title'] }} : {{ $ring['center'] }} {{ $ring['sub'] }}">
            <circle class="ring-track" cx="74" cy="74" r="{{ $radius }}" />
            @foreach ($visible as $segment)
                @php($length = $visible->count() > 1 ? max($segment['share'] - $gap, 0.4) : $segment['share'])
                <circle class="ring-seg tone-{{ $segment['tone'] }}" cx="74" cy="74" r="{{ $radius }}" pathLength="100"
                        stroke-dasharray="{{ round($length, 3) }} {{ round(100 - $length, 3) }}" stroke-dashoffset="{{ round(-$offset, 3) }}"
                        tabindex="0" data-tip="{{ $segment['label'] }}&#10;{{ $segment['value'] }} ({{ round($segment['share']) }} %)" aria-label="{{ $segment['label'] }} : {{ $segment['value'] }}" />
                @php($offset += $segment['share'])
            @endforeach
        </svg>
        <div class="ring-center"><strong>{{ $ring['center'] }}</strong><span>{{ $ring['sub'] }}</span></div>
    </div>
    <ul class="ring-legend">
        @foreach ($ring['segments'] as $segment)
            <li class="tone-{{ $segment['tone'] }}"><i></i><span>{{ $segment['label'] }}</span><b>{{ $segment['value'] }}</b></li>
        @endforeach
    </ul>
    <p class="ring-note">{{ $ring['note'] }}</p>
</div>
