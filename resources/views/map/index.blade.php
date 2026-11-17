@extends('layouts.app')
@section('title', __('Carte'))

@push('head')<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">@endpush

@section('content')
    <div class="page-head"><h1>{{ __('Carte des compteurs') }}</h1></div>

    <form class="filters" method="GET">
        <div>
            <label for="zone_id">{{ __('Zone') }}</label>
            <select id="zone_id" name="zone_id" onchange="this.form.submit()">
                <option value="">{{ __('Toutes') }}</option>
                @foreach ($zones as $zone)
                    <option value="{{ $zone->id }}" @selected($zoneId == $zone->id)>{{ $zone->name }}</option>
                @endforeach
            </select>
        </div>
    </form>

    <div class="card">
        <div class="location-bar">
            <p id="location-status" class="small">{{ __('Activez la localisation pour voir votre position sur la carte.') }}</p>
            <button type="button" class="btn ghost small" id="my-position">{{ __('Ma position') }}</button>
        </div>
        <div id="map"></div>
        <div class="map-legend small">
            <span><i style="background: #059669"></i> {{ __('En règle') }}</span>
            <span><i style="background: #dc2626"></i> {{ __('Non en règle') }}</span>
            <span><i style="background: #94a3b8"></i> {{ __('Aucun client') }}</span>
            <span><i style="background: #0ea5e9"></i> {{ __('Vous') }}</span>
        </div>
        <p class="soft small">{{ __(':v1 compteur(s) sur la carte. Cliquez sur un compteur pour voir son client et ses paiements.', ['v1' => count($points)]) }}
            @if ($withoutPosition > 0){{ __(':v1 sans position : renseignez-la dans la fiche du compteur.', ['v1' => $withoutPosition]) }}@endif
        </p>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
    <script src="{{ asset('js/location.js') }}"></script>
    <script>
        (function () {
            var points = @json($points);
            var statuses = {
                ok: { label: {{ Js::from(__('En règle')) }}, badge: 'ok', color: '#059669' },
                late: { label: {{ Js::from(__('Non en règle')) }}, badge: 'ko', color: '#dc2626' },
                none: { label: {{ Js::from(__('Aucun client')) }}, badge: 'off', color: '#94a3b8' }
            };
            var map = L.map('map');
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }).addTo(map);

            function line(parent, text, tag) {
                var element = document.createElement(tag || 'div');
                element.textContent = text;
                parent.appendChild(element);
                return element;
            }

            function popupFor(point) {
                var popup = document.createElement('div');
                var status = statuses[point.status];
                popup.className = 'meter-popup';

                var title = line(popup, {{ Js::from(__('Compteur ')) }} + point.number, 'a');
                title.href = point.url;

                line(popup, status.label, 'span').className = 'badge ' + status.badge;
                if (point.customer) {
                    line(popup, point.customer, 'strong');
                }
                if (point.phone) {
                    line(popup, point.phone, 'a').href = 'tel:' + point.phone.replace(/\s/g, '');
                }
                if (point.status === 'late') {
                    line(popup, {{ Js::from(__('Impayé : ')) }} + point.unpaid + {{ Js::from(__(' sur ')) }} + point.unpaid_months + {{ Js::from(__(' mois')) }}).className = 'danger-text';
                }
                if (point.zone) {
                    line(popup, {{ Js::from(__('Zone : ')) }} + point.zone).className = 'soft';
                }
                return popup;
            }

            var markers = points.map(function (point) {
                return L.circleMarker([point.lat, point.lng], {
                    radius: 10, weight: 2, color: '#fff', fillOpacity: 1,
                    fillColor: statuses[point.status].color
                }).bindPopup(popupFor(point)).addTo(map);
            });

            if (markers.length) {
                map.fitBounds(L.featureGroup(markers).getBounds().pad(0.2), { maxZoom: 16 });
            } else {
                map.setView([5, 15], 3);
            }

            var status = document.getElementById('location-status');
            var userLayer = null;

            function locate(recenter) {
                locateUser(function (coords) {
                    userLayer = showUserPosition(map, coords, userLayer);
                    if (recenter) {
                        map.setView([coords.latitude, coords.longitude], 16);
                    }
                    setLocationStatus(status, {{ Js::from(__('Votre position est affichée en bleu, précision d\'environ ')) }} + Math.round(coords.accuracy) + ' m.');
                }, function (message) {
                    setLocationStatus(status, message, true);
                });
            }

            locate(!markers.length);
            document.getElementById('my-position').addEventListener('click', function () {
                locate(true);
            });
        })();
    </script>
@endpush
