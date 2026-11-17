@extends('layouts.app')
@section('title', $meter->exists ? __('Modifier le compteur') : __('Nouveau compteur'))

@push('head')<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">@endpush

@section('content')
    <div class="page-head"><h1>{{ $meter->exists ? __('Modifier le compteur') : __('Nouveau compteur') }}</h1></div>
    <div class="card narrow">
        <form method="POST" action="{{ $meter->exists ? route('meters.update', $meter) : route('meters.store') }}">
            @csrf
            @if ($meter->exists) @method('PUT') @endif
            <div class="form-grid">
                <x-field name="number" :label="__('Numéro du compteur')" :value="$meter->number" :required="true" autofocus />
                <x-field name="installed_at" :label="__('Date de pose')" type="date" :value="$meter->installed_at?->format('Y-m-d')" />
            </div>
            <div class="form-grid">
                <x-field name="customer_id" :label="__('Client')" :help="__('À renseigner pour pouvoir saisir des relevés. Les factures passées restent au client d\'origine.')">
                    <select id="customer_id" name="customer_id">
                        <option value="">{{ __('— Non rattaché —') }}</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected(old('customer_id', $meter->customer_id) == $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="zone_id" :label="__('Zone géographique (facultatif)')">
                    <select id="zone_id" name="zone_id">
                        <option value="">{{ __('— Aucune —') }}</option>
                        @foreach ($zones as $zone)
                            <option value="{{ $zone->id }}" @selected(old('zone_id', $meter->zone_id) == $zone->id)>{{ $zone->name }}</option>
                        @endforeach
                    </select>
                </x-field>
            </div>
            <x-field name="initial_index" :label="__('Index de départ (m³)')" type="number" step="0.001" min="0" :required="true" :value="(float) $meter->initial_index"
                     :help="$hasReadings ? __('Le corriger recalcule aussi le premier relevé de ce compteur.') : __('Index à la pose, ou dernier index relevé dans votre ancien système.')" />

            <h2>{{ __('Position sur la carte (facultatif)') }}</h2>
            <div class="location-bar">
                <p id="location-status" class="small">{{ __('Activez la localisation pour placer le compteur là où vous êtes. Vous pouvez aussi cliquer sur la carte ou saisir ses coordonnées.') }}</p>
                <button type="button" class="btn ghost small" id="use-location">{{ __('Placer à ma position') }}</button>
            </div>
            <div id="picker"></div>
            <div class="form-grid">
                <x-field name="latitude" :label="__('Latitude')" type="number" step="any" min="-90" max="90" :value="$meter->latitude" />
                <x-field name="longitude" :label="__('Longitude')" type="number" step="any" min="-180" max="180" :value="$meter->longitude" />
            </div>
            <div class="field">
                <label class="check">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $meter->is_active))> {{ __('Compteur actif') }}
                </label>
            </div>
            <button type="submit" class="btn">{{ __('Enregistrer') }}</button>
            <a class="btn ghost" href="{{ $meter->exists ? route('meters.show', $meter) : route('meters.index') }}">{{ __('Annuler') }}</a>
        </form>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
    <script src="{{ asset('js/location.js') }}"></script>
    <script>
        (function () {
            var status = document.getElementById('location-status');
            var lat = document.getElementById('latitude');
            var lng = document.getElementById('longitude');
            var map = L.map('picker');
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }).addTo(map);

            var marker = null;
            function place(latitude, longitude) {
                if (marker) { marker.setLatLng([latitude, longitude]); }
                else { marker = L.marker([latitude, longitude]).addTo(map); }
            }

            var userLayer = null;
            function showUser(coords) {
                userLayer = showUserPosition(map, coords, userLayer);
            }

            function placeAtUser(coords) {
                lat.value = coords.latitude.toFixed(7);
                lng.value = coords.longitude.toFixed(7);
                place(lat.value, lng.value);
                showUser(coords);
                map.setView([lat.value, lng.value], 17);
                var accuracy = Math.round(coords.accuracy);
                setLocationStatus(status, {{ Js::from(__('Compteur placé à votre position actuelle, précision d\'environ ')) }} + accuracy + ' m. '
                    + (accuracy > 100 ? {{ Js::from(__('C\'est approximatif : cliquez sur la carte pour corriger. ')) }} : '')
                    + {{ Js::from(__('Pensez à enregistrer.')) }});
            }

            function showError(message) {
                setLocationStatus(status, message, true);
            }

            if (lat.value && lng.value) {
                place(lat.value, lng.value);
                map.setView([lat.value, lng.value], 16);
            } else {
                map.setView([5, 15], 3);

                if (@json(! $meter->exists)) {
                    // À l'ajout, demande l'autorisation et prend la position actuelle, sauf
                    // si une position a été choisie à la main pendant la recherche.
                    setLocationStatus(status, {{ Js::from(__('Autorisez la localisation pour prendre la position actuelle du compteur…')) }});
                    locateUser(function (coords) {
                        if (lat.value || lng.value) {
                            showUser(coords);
                        } else {
                            placeAtUser(coords);
                        }
                    }, showError);
                } else {
                    locateUser(function (coords) {
                        showUser(coords);
                        map.setView([coords.latitude, coords.longitude], 16);
                        setLocationStatus(status, {{ Js::from(__('Votre position est en bleu. Si vous êtes devant le compteur, cliquez sur « Placer à ma position ».')) }});
                    }, showError);
                }
            }

            document.getElementById('use-location').addEventListener('click', function () {
                if (lat.value && lng.value && !confirm({{ Js::from(__('Remplacer la position actuelle du compteur ?')) }})) {
                    return;
                }
                setLocationStatus(status, {{ Js::from(__('Recherche de votre position…')) }});
                locateUser(placeAtUser, showError);
            });

            map.on('click', function (event) {
                lat.value = event.latlng.lat.toFixed(7);
                lng.value = event.latlng.lng.toFixed(7);
                place(lat.value, lng.value);
            });
            [lat, lng].forEach(function (input) {
                input.addEventListener('change', function () {
                    if (lat.value && lng.value) {
                        place(lat.value, lng.value);
                        map.setView([lat.value, lng.value], Math.max(map.getZoom(), 14));
                    }
                });
            });
        })();
    </script>
@endpush
