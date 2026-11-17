@extends('layouts.app')
@section('title', $reader ? __('Modifier le releveur') : __('Ajouter un releveur'))

@php
    $access = old('access', $reader && ! $reader->all_zones ? 'zones' : 'all');
    $selected = collect(old('zone_ids', $reader?->zones->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id);
@endphp

@section('content')
    <div class="page-head"><h1>{{ $reader ? __('Modifier le releveur') : __('Ajouter un releveur') }}</h1></div>

    <div class="card narrow">
        <form method="POST" action="{{ $reader ? route('readers.update', $reader->id) : route('readers.store') }}">
            @csrf
            @if ($reader) @method('PUT') @endif
            <x-field name="name" :label="__('Nom')" :value="$reader?->name" :required="true" />
            <x-field name="email" :label="__('Login (e-mail)')" type="email" :value="$reader?->email" :required="true" autocomplete="off" />

            <fieldset class="zone-access" data-zone-access>
                <legend>{{ __('Zones du releveur') }}</legend>
                <label class="zone-choice"><input type="radio" name="access" value="all" @checked($access === 'all')> {{ __('Toutes les zones') }}</label>
                <label class="zone-choice"><input type="radio" name="access" value="zones" @checked($access === 'zones')> {{ __('Certaines zones') }}</label>

                <div class="zone-picker" data-zone-picker @if ($access !== 'zones') hidden @endif>
                    <div class="zone-add">
                        <label for="zone_to_add">{{ __('Zone') }}</label>
                        <div class="zone-add-row">
                            <select id="zone_to_add" data-zone-select>
                                <option value="">{{ __('Choisir…') }}</option>
                                @foreach ($zones as $zone)
                                    <option value="{{ $zone->id }}" @disabled($selected->contains($zone->id))>{{ $zone->name }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn ghost" data-zone-add>{{ __('Ajouter') }}</button>
                        </div>
                    </div>
                    <ul class="zone-list" data-zone-list>
                        @foreach ($zones->whereIn('id', $selected) as $zone)
                            <li data-zone-item="{{ $zone->id }}">
                                <span>{{ $zone->name }}</span>
                                <input type="hidden" name="zone_ids[]" value="{{ $zone->id }}">
                                <button type="button" class="icon-btn" data-zone-remove aria-label="{{ __('Retirer :v1', ['v1' => $zone->name]) }}" title="{{ __('Retirer') }}"><x-icon name="x" /></button>
                            </li>
                        @endforeach
                    </ul>
                    <p class="help" data-zone-empty @if ($selected->isNotEmpty()) hidden @endif>{{ __('Ajoutez au moins une zone.') }}</p>
                    @error('zone_ids')<div class="error">{{ $message }}</div>@enderror
                </div>
            </fieldset>

            <div class="form-grid">
                <x-field name="password" :label="$reader ? __('Nouveau mot de passe temporaire') : __('Mot de passe temporaire')" type="password" :required="! $reader" minlength="8" autocomplete="new-password" :help="$reader ? __('Laissez vide pour ne pas le changer.') : __('Il devra le changer à sa première connexion.')" />
                <x-field name="password_confirmation" :label="__('Confirmer')" type="password" :required="! $reader" autocomplete="new-password" />
            </div>
            <button type="submit" class="btn">{{ __('Enregistrer') }}</button>
            <a class="btn ghost" href="{{ route('readers.index') }}">{{ __('Annuler') }}</a>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var box = document.querySelector('[data-zone-access]');
            var picker = box.querySelector('[data-zone-picker]');
            var select = box.querySelector('[data-zone-select]');
            var list = box.querySelector('[data-zone-list]');
            var empty = box.querySelector('[data-zone-empty]');
            var removeLabel = {{ Js::from(__('Retirer')) }};

            box.querySelectorAll('input[name="access"]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    picker.hidden = box.querySelector('input[name="access"]:checked').value !== 'zones';
                });
            });

            function setDisabled(id, disabled) {
                var option = select.querySelector('option[value="' + id + '"]');
                if (option) { option.disabled = disabled; }
            }

            function refreshEmpty() {
                empty.hidden = list.children.length > 0;
            }

            box.querySelector('[data-zone-add]').addEventListener('click', function () {
                var option = select.options[select.selectedIndex];
                if (!option || !option.value) { return; }

                var item = document.createElement('li');
                item.dataset.zoneItem = option.value;

                var name = document.createElement('span');
                name.textContent = option.textContent;

                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'zone_ids[]';
                input.value = option.value;

                var remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'icon-btn';
                remove.dataset.zoneRemove = '';
                remove.title = removeLabel;
                remove.setAttribute('aria-label', removeLabel + ' ' + option.textContent);
                remove.innerHTML = '&times;';

                item.append(name, input, remove);
                list.appendChild(item);
                option.disabled = true;
                select.value = '';
                refreshEmpty();
            });

            list.addEventListener('click', function (event) {
                var remove = event.target.closest('[data-zone-remove]');
                if (!remove) { return; }
                var item = remove.closest('[data-zone-item]');
                setDisabled(item.dataset.zoneItem, false);
                item.remove();
                refreshEmpty();
            });
        })();
    </script>
@endpush
