<div class="water-backdrop" aria-hidden="true">
    {{-- Château d'eau, en arrière-plan --}}
    <svg class="water-tower" viewBox="0 0 420 760" preserveAspectRatio="xMaxYMax meet">
        <path d="M210 62v30" fill="none" stroke="currentColor" stroke-width="6"/>
        <circle cx="210" cy="56" r="8" fill="currentColor"/>
        <path d="M70 230Q70 122 210 90Q350 122 350 230Z" fill="currentColor"/>
        <rect x="62" y="230" width="296" height="10" rx="3" fill="currentColor"/>
        <rect x="70" y="240" width="280" height="84" fill="currentColor" opacity=".75"/>
        <path d="M70 324H350L290 394H130Z" fill="currentColor"/>
        <rect x="186" y="394" width="48" height="366" fill="currentColor" opacity=".75"/>
        <g fill="none" stroke="currentColor" stroke-width="10" stroke-linecap="round">
            <path d="M136 394 66 756M284 394 354 756"/>
        </g>
        <g fill="none" stroke="currentColor" stroke-width="5">
            <path d="M118 490H302M92 620H328"/>
            <path d="M118 490 186 620M302 490 234 620M92 620 186 756M328 620 234 756"/>
        </g>
        <path d="M30 757H390" fill="none" stroke="currentColor" stroke-width="6" stroke-linecap="round"/>
    </svg>

    {{-- Vagues : le motif fait deux fois la largeur de l'écran et se répète, la boucle ne se voit donc pas --}}
    <div class="water-waves">
        @foreach (['back', 'middle', 'front'] as $layer)
            <svg class="wave wave-{{ $layer }}" viewBox="0 0 2400 120" preserveAspectRatio="none">
                <path d="M0 60Q150 20 300 60T600 60T900 60T1200 60T1500 60T1800 60T2100 60T2400 60V120H0Z" fill="currentColor"/>
            </svg>
        @endforeach
    </div>

    {{-- Robinet et eau qui coule --}}
    <svg class="water-tap" viewBox="0 0 320 800" preserveAspectRatio="xMinYMin meet">
        <defs>
            <linearGradient id="water-stream" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#0ea5e9" stop-opacity=".8"/>
                <stop offset="1" stop-color="#2563eb" stop-opacity=".55"/>
            </linearGradient>
        </defs>

        <g class="tap-water">
            <path class="stream-body" d="M203 212C205 320 208 500 209 772H223C224 500 227 320 229 212Z" fill="url(#water-stream)"/>
            <g class="stream-flow" fill="none" stroke-linecap="round">
                <path d="M211 216V772" stroke="#e0f2fe" stroke-width="2.5"/>
                <path class="slow" d="M218 216V772" stroke="#bae6fd" stroke-width="2"/>
            </g>
            <ellipse cx="216" cy="776" rx="46" ry="8" fill="#2563eb" opacity=".22"/>
            <ellipse class="ripple" cx="216" cy="776" rx="30" ry="6"/>
            <ellipse class="ripple late" cx="216" cy="776" rx="30" ry="6"/>
            <circle class="splash left" cx="208" cy="768" r="3.5"/>
            <circle class="splash right" cx="224" cy="768" r="3"/>
        </g>

        <g class="tap-body">
            <rect x="0" y="120" width="56" height="40"/>
            <rect x="50" y="104" width="18" height="72" rx="4"/>
            <path d="M68 122H190Q236 122 236 168V200H196V172Q196 160 184 160H68Z"/>
            <rect x="190" y="198" width="52" height="14" rx="4"/>
            <rect x="116" y="96" width="28" height="28" rx="3"/>
            <rect x="124" y="66" width="12" height="32"/>
            <rect x="84" y="54" width="92" height="14" rx="7"/>
        </g>
    </svg>
</div>
