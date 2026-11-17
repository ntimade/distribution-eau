@extends('layouts.app')
@section('title', __('Tableau de bord'))

@push('head')<link rel="stylesheet" href="{{ asset_v('css/dashboard.css') }}">@endpush

@php
    $axis = fn ($value) => $value >= 1000 ? rtrim(rtrim(number_format($value / 1000, 1, ',', ' '), '0'), ',').' k' : (string) $value;
    $chartMax = max($chartTicks) ?: 1;
    $general = $scope['view'] === 'general';
    $selectedMonth = \Illuminate\Support\Carbon::createFromFormat('!Y-m', $period);
@endphp

@section('content')
    <div class="page-head dash-hero">
        <div>
            <h1>{{ __('Tableau de bord') }}</h1>
            <p class="dash-sub">
                {{ $general ? __('Situation au ').now()->format('d/m/Y') : ($scope['view'] === 'mois' ? __('Mois de ').$scope['label'] : __('Année ').$scope['label']) }}
                · {{ $scope['view'] === 'mois' ? __(':v1 compteur(s) non relevé(s)', ['v1' => $pendingCount]) : __(':v1 clients, :v2 compteurs', ['v1' => $customersCount, 'v2' => $metersCount]) }}
            </p>
        </div>
        <a class="btn" href="{{ route('readings.create') }}">{{ __('Saisir un relevé') }}</a>
    </div>

    <div class="dash-filter">
        <nav class="dash-views" aria-label="{{ __('Période affichée') }}">
            @foreach (['general' => __('Vue générale'), 'mois' => __('Mensuel'), 'annee' => __('Annuel')] as $key => $title)
                <a href="{{ route('dashboard', $key === 'general' ? [] : ['vue' => $key]) }}" @class(['active' => $scope['view'] === $key]) @if ($scope['view'] === $key) aria-current="page" @endif>{{ $title }}</a>
            @endforeach
        </nav>

        @if ($scope['view'] === 'mois')
            <form class="dash-period" method="GET">
                <input type="hidden" name="vue" value="mois">
                <a class="btn ghost small" href="{{ route('dashboard', ['vue' => 'mois', 'mois' => $selectedMonth->copy()->subMonth()->format('Y-m')]) }}" aria-label="{{ __('Mois précédent') }}" title="{{ __('Mois précédent') }}">←</a>
                <input type="month" name="mois" value="{{ $scope['value'] }}" max="{{ now()->format('Y-m') }}" aria-label="{{ __('Mois') }}">
                @if ($scope['value'] < now()->format('Y-m'))
                    <a class="btn ghost small" href="{{ route('dashboard', ['vue' => 'mois', 'mois' => $selectedMonth->copy()->addMonth()->format('Y-m')]) }}" aria-label="{{ __('Mois suivant') }}" title="{{ __('Mois suivant') }}">→</a>
                @endif
                <button class="btn small">{{ __('Afficher') }}</button>
            </form>
        @elseif ($scope['view'] === 'annee')
            <form class="dash-period" method="GET">
                <input type="hidden" name="vue" value="annee">
                <select name="annee" aria-label="{{ __('Année') }}">
                    @foreach ($years as $year)
                        <option value="{{ $year }}" @selected($scope['value'] === (string) $year)>{{ $year }}</option>
                    @endforeach
                </select>
                <button class="btn small">{{ __('Afficher') }}</button>
            </form>
        @endif
    </div>

    @if ($general && $actions)
        <div class="card dash-todo">
            @foreach ($actions as $action)
                <a class="dash-todo-row {{ $action['level'] }}" href="{{ $action['url'] }}" title="{{ $action['text'] }}">
                    <x-icon :name="$action['icon']" />
                    <strong>{{ $action['title'] }}</strong>
                    <span>{{ $action['cta'] }} →</span>
                </a>
            @endforeach
        </div>
    @elseif ($general)
        <div class="card dash-ok"><x-icon name="droplet" /> {{ __('Rien d\'urgent à traiter aujourd\'hui.') }}</div>
    @endif

    <div class="stats dash-stats">
        <div class="stat alert">
            <x-icon name="circle-alert" class="stat-icon" />
            <span>{{ $scope['unpaidTitle'] }}</span><strong>{{ money($totalUnpaid) }}</strong>
            <small>{{ __('dont :v1 en retard', ['v1' => money($overdueAmount)]) }}</small>
        </div>
        <div class="stat">
            <x-icon name="wallet" class="stat-icon" />
            <span>{{ __('Encaissé en :v1', ['v1' => $scope['label']]) }}</span><strong>{{ money($collected) }}</strong>
            <small>{{ $scope['compareLabel'] }} : {{ money($lastCollected) }}</small>
        </div>
        <div class="stat">
            <x-icon name="receipt" class="stat-icon" />
            <span>{{ __('Facturé en :v1', ['v1' => $scope['label']]) }}</span><strong>{{ money($billed) }}</strong>
            <small>{{ $scope['compareLabel'] }} : {{ money($lastBilled) }}</small>
        </div>
        <div class="stat">
            <x-icon name="droplet" class="stat-icon" />
            <span>{{ __('Volume relevé en :v1', ['v1' => $scope['label']]) }}</span><strong>{{ qty($volume) }} m³</strong>
            <small>{{ $scope['compareLabel'] }} : {{ qty($lastVolume) }} m³</small>
        </div>
    </div>

    <div class="ring-grid">
        @foreach ($rings as $ring)
            <x-ring :ring="$ring" />
        @endforeach
    </div>

    <div class="card">
        <h2>{{ __('Facturé et encaissé par mois') }}</h2>
        <div class="chart-legend">
            <span><i class="chart-1"></i>{{ __('Facturé') }}</span>
            <span><i class="chart-2"></i>{{ __('Encaissé') }}</span>
        </div>
        <div class="chart">
            <div class="chart-plot">
                @foreach ($chartTicks as $tick)
                    <div class="chart-grid" style="bottom: {{ $tick / $chartMax * 100 }}%"><span>{{ $axis($tick) }}</span></div>
                @endforeach
                <div class="chart-cols">
                    @foreach ($months as $month)
                        <div class="chart-col" tabindex="0" data-tip="{{ period_label($month['period']) }}&#10;{{ __('Facturé : :v1', ['v1' => money($month['billed'])]) }}&#10;{{ __('Encaissé : :v1', ['v1' => money($month['collected'])]) }}" aria-label="{{ __(':v1 : facturé :v2, encaissé :v3', ['v1' => period_label($month['period']), 'v2' => money($month['billed']), 'v3' => money($month['collected'])]) }}">
                            <i class="chart-1" style="height: {{ $month['billed'] / $chartMax * 100 }}%"></i>
                            <i class="chart-2" style="height: {{ $month['collected'] / $chartMax * 100 }}%"></i>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="chart-labels">
                @foreach ($months as $month)<span>{{ $month['short'] }}</span>@endforeach
            </div>
        </div>
        <details class="chart-table">
            <summary>{{ __('Voir les chiffres') }}</summary>
            <p class="dash-note">{{ __('Le facturé suit le mois de la facture et l\'encaissé la date du paiement. Les soldes de départ ne sont pas comptés dans le facturé.') }}</p>
            <div class="table-wrap"><table data-sortable="months">
                <tr><th data-kind="date">{{ __('Mois') }}</th><th class="right" data-kind="number">{{ __('Volume') }}</th><th class="right" data-kind="number">{{ __('Facturé') }}</th><th class="right" data-kind="number">{{ __('Encaissé') }}</th></tr>
                @foreach ($months->reverse() as $month)
                    <tr>
                        <td data-sort="{{ $month['period'] }}">{{ period_label($month['period']) }}</td>
                        <td class="right" data-sort="{{ (float) $month['volume'] }}">{{ qty($month['volume']) }} m³</td>
                        <td class="right" data-sort="{{ (float) $month['billed'] }}">{{ money($month['billed']) }}</td>
                        <td class="right" data-sort="{{ (float) $month['collected'] }}">{{ money($month['collected']) }}</td>
                    </tr>
                @endforeach
            </table></div>
        </details>
    </div>

    {{-- Le détail reste replié : on l'ouvre quand on en a besoin. --}}
    <div class="dash-grid">
        @if ($insights)
            <details class="card fold dash-fold" id="insights">
                <summary><h2>{{ __('À retenir') }}</h2><span class="badge off">{{ count($insights) }}</span></summary>
                <ul class="dash-insights">
                    @foreach ($insights as $insight)<li>{{ $insight }}</li>@endforeach
                </ul>
            </details>
        @endif

        <details class="card fold dash-fold" id="debtors">
            <summary><h2>{{ __('Plus gros impayés') }}</h2><span class="badge off">{{ $debtors->count() }}</span></summary>
            @if ($debtors->isEmpty())
                <p class="empty">{{ __('Aucun impayé.') }}</p>
            @else
                <div class="table-wrap"><table data-sortable="debtors">
                    <tr><th>{{ __('Client') }}</th><th class="right" data-kind="number">{{ __('Dû') }}</th><th class="right" data-kind="number">{{ __('Part') }}</th><th class="right" data-kind="number">{{ __('Retard') }}</th></tr>
                    @foreach ($debtors as $debtor)
                        <tr>
                            <td data-sort="{{ $debtor->customer->name }}">
                                <a href="{{ route('customers.show', $debtor->customer) }}">{{ $debtor->customer->name }}</a>
                                @if ($debtor->customer->phone)<br><a class="soft small" href="tel:{{ str_replace(' ', '', $debtor->customer->phone) }}">{{ $debtor->customer->phone }}</a>@endif
                            </td>
                            <td class="right danger-text" data-sort="{{ (float) $debtor->amount }}">{{ money($debtor->amount) }}</td>
                            <td class="right" data-sort="{{ (float) $debtor->share }}">{{ round($debtor->share) }} %</td>
                            <td class="right" data-sort="{{ (int) $debtor->days_late }}">{{ $debtor->days_late > 0 ? __(':v1 j', ['v1' => $debtor->days_late]) : '—' }}</td>
                        </tr>
                    @endforeach
                </table></div>
            @endif
        </details>

        @if ($zones->isNotEmpty())
            <details class="card fold dash-fold" id="zones">
                <summary><h2>{{ __('Impayés par zone') }}</h2><span class="badge off">{{ $zones->count() }}</span></summary>
                <div data-sortable="zones" data-fields="amount:number:Impayés,name:text:Zone,meters:number:Compteurs,volume:number:Volume">
                    @foreach ($zones as $zone)
                        <div class="hbar" data-amount="{{ (float) $zone['amount'] }}" data-name="{{ $zone['name'] }}" data-meters="{{ (int) $zone['meters'] }}" data-volume="{{ (float) $zone['volume'] }}">
                            <div class="hbar-head">
                                <span>{{ $zone['name'] }} <span class="soft small">{{ __('· :v1 compteur(s), :v2 m³ :v3', ['v1' => $zone['meters'], 'v2' => qty($zone['volume']), 'v3' => $general ? __('ce mois-ci') : __('sur la période')]) }}</span></span>
                                <span>{{ money($zone['amount']) }}</span>
                            </div>
                            <div class="hbar-track"><span class="hbar-fill" style="width: {{ $zone['share'] }}%"></span></div>
                        </div>
                    @endforeach
                </div>
            </details>
        @endif

        @if ($general)
        <details class="card fold dash-fold" id="anomalies">
            <summary><h2>{{ __('Consommations inhabituelles') }}</h2><span class="badge {{ $anomalies->isEmpty() ? 'off' : 'partial' }}">{{ $anomalyCount }}</span></summary>
            <p class="dash-note">{{ __('Dernier relevé comparé à la moyenne du compteur. À vérifier sur le terrain : ce n\'est pas une preuve.') }}</p>
            @if ($anomalyCount > $anomalies->count())
                <p class="dash-note">{{ __('Les :v1 écarts les plus marqués sur :v2.', ['v1' => $anomalies->count(), 'v2' => $anomalyCount]) }}</p>
            @endif
            @if ($anomalies->isEmpty())
                <p class="empty">{{ __('Rien d\'inhabituel.') }}</p>
            @else
                <div class="table-wrap"><table data-sortable="anomalies">
                    <tr><th>{{ __('Compteur') }}</th><th class="right" data-kind="number">{{ __('Relevé') }}</th><th class="right" data-kind="number">{{ __('Moyenne') }}</th></tr>
                    @foreach ($anomalies as $anomaly)
                        <tr>
                            <td class="wrap" data-sort="{{ $anomaly->reading->meter->number }}">
                                <a href="{{ route('readings.show', $anomaly->reading) }}">{{ $anomaly->reading->meter->number }}</a> — {{ $anomaly->reading->customer->name }}
                                <br><span class="soft small">{{ $anomaly->problem }}</span>
                            </td>
                            <td class="right" data-sort="{{ (float) $anomaly->consumption }}">{{ qty($anomaly->consumption) }} m³</td>
                            <td class="right" data-sort="{{ (float) $anomaly->average }}">{{ qty($anomaly->average) }} m³</td>
                        </tr>
                    @endforeach
                </table></div>
            @endif
        </details>
        @endif

        <details class="card fold dash-fold" id="payments">
            <summary><h2>{{ $general ? __('Derniers paiements') : __('Derniers paiements de la période') }}</h2><span class="badge off">{{ $latestPayments->count() }}</span></summary>
            @if ($latestPayments->isEmpty())
                <p class="empty">{{ $general ? __('Aucun paiement enregistré.') : __('Aucun paiement sur cette période.') }}</p>
            @else
                <div class="table-wrap"><table data-sortable="payments">
                    <tr><th data-kind="date">{{ __('Date') }}</th><th>{{ __('Client') }}</th><th data-kind="date">{{ __('Mois') }}</th><th class="right" data-kind="number">{{ __('Montant') }}</th></tr>
                    @foreach ($latestPayments as $payment)
                        <tr>
                            <td data-sort="{{ $payment->paid_at->format('Y-m-d') }}">{{ $payment->paid_at->format('d/m/Y') }}</td>
                            <td>{{ $payment->reading->customer->name }}</td>
                            <td data-sort="{{ $payment->reading->period }}">{{ $payment->reading->label() }}</td>
                            <td class="right" data-sort="{{ (float) $payment->amount }}">{{ money($payment->amount) }}</td>
                        </tr>
                    @endforeach
                </table></div>
                <p class="dash-more"><a class="small" href="{{ route('payments.index') }}">{{ __('Tout voir') }}</a></p>
            @endif
        </details>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var tip = document.createElement('div');
            tip.className = 'chart-tip';
            tip.hidden = true;
            document.body.appendChild(tip);

            function show(column) {
                var box = column.getBoundingClientRect();
                tip.textContent = column.dataset.tip;
                tip.hidden = false;
                tip.style.top = Math.max(8, box.top - tip.offsetHeight - 6) + 'px';
                tip.style.left = Math.min(window.innerWidth - tip.offsetWidth - 8, Math.max(8, box.left + box.width / 2 - tip.offsetWidth / 2)) + 'px';
            }

            document.querySelectorAll('[data-tip]').forEach(function (column) {
                column.addEventListener('mouseenter', function () { show(column); });
                column.addEventListener('focus', function () { show(column); });
                column.addEventListener('mouseleave', function () { tip.hidden = true; });
                column.addEventListener('blur', function () { tip.hidden = true; });
            });
        })();

        // Tri facultatif des listes : l'ordre habituel reste celui du serveur, et le choix est retenu sur cet appareil.
        (function () {
            var firstDirection = { text: 'asc', number: 'desc', date: 'desc' };
            var wording = {
                text: { asc: 'A → Z', desc: 'Z → A' },
                number: { desc: {{ Js::from(__('décroissant')) }}, asc: {{ Js::from(__('croissant')) }} },
                date: { desc: {{ Js::from(__('récent d\'abord')) }}, asc: {{ Js::from(__('ancien d\'abord')) }} }
            };

            function remembered(name) {
                try { return localStorage.getItem('dash-sort-' + name) || ''; } catch (error) { return ''; }
            }

            function remember(name, value) {
                try {
                    if (value) { localStorage.setItem('dash-sort-' + name, value); } else { localStorage.removeItem('dash-sort-' + name); }
                } catch (error) {}
            }

            document.querySelectorAll('[data-sortable]').forEach(function (container) {
                var name = container.dataset.sortable;
                var isTable = container.tagName === 'TABLE';
                var heads = isTable ? Array.prototype.slice.call(container.querySelectorAll('th')) : [];
                var items = isTable
                    ? Array.prototype.filter.call(container.rows, function (row) { return row.cells[0].tagName === 'TD'; })
                    : Array.prototype.slice.call(container.children);

                var fields = isTable
                    ? heads.map(function (th, index) { return { key: String(index), kind: th.dataset.kind || 'text', label: th.textContent.trim() }; })
                    : container.dataset.fields.split(',').map(function (field) {
                        var parts = field.split(':');
                        return { key: parts[0], kind: parts[1], label: parts[2] };
                    });

                function valueOf(item, field) {
                    if (!isTable) { return item.dataset[field.key]; }
                    var cell = item.cells[Number(field.key)];
                    return cell.dataset.sort !== undefined ? cell.dataset.sort : cell.textContent.trim();
                }

                var select = document.createElement('select');
                select.setAttribute('aria-label', {{ Js::from(__('Trier cette liste')) }});
                select.add(new Option({{ Js::from(__('Ordre habituel')) }}, ''));
                fields.forEach(function (field) {
                    ['asc', 'desc'].sort(function (a) { return a === firstDirection[field.kind] ? -1 : 1; }).forEach(function (direction) {
                        select.add(new Option(field.label + ' : ' + wording[field.kind][direction], field.key + ':' + direction));
                    });
                });

                var bar = document.createElement('label');
                bar.className = 'sort-bar' + (isTable ? ' phone-only' : '');
                bar.append({{ Js::from(__('Trier par ')) }}, select);
                (isTable ? container.closest('.table-wrap') : container).before(bar);

                function apply(value) {
                    var parts = value.split(':');
                    var field = fields.find(function (candidate) { return candidate.key === parts[0]; });
                    var sign = parts[1] === 'desc' ? -1 : 1;
                    var order = items.slice();

                    if (field) {
                        order.sort(function (a, b) {
                            var left = valueOf(a, field);
                            var right = valueOf(b, field);
                            var result = field.kind === 'number'
                                ? Number(left) - Number(right)
                                : left.localeCompare(right, 'fr', { numeric: true, sensitivity: 'base' });
                            return (result * sign) || items.indexOf(a) - items.indexOf(b);
                        });
                    }

                    order.forEach(function (item) { item.parentNode.appendChild(item); });
                    shown = order;
                    showPage(0);
                    select.value = field ? value : '';
                    heads.forEach(function (th, index) {
                        var active = field && field.key === String(index);
                        th.setAttribute('aria-sort', active ? (sign === 1 ? 'ascending' : 'descending') : 'none');
                    });
                }

                // Au plus 10 lignes à la fois, dans l'ordre de tri choisi.
                var pageSize = 10;
                var shown = items;
                var page = 0;
                var pager = null;

                function showPage(number) {
                    page = number;
                    shown.forEach(function (item, index) { item.hidden = Math.floor(index / pageSize) !== page; });
                    if (pager) {
                        var last = Math.ceil(shown.length / pageSize) - 1;
                        pager.children[0].disabled = page === 0;
                        pager.children[2].disabled = page === last;
                        pager.children[1].textContent = (page + 1) + ' / ' + (last + 1);
                    }
                }

                if (items.length > pageSize) {
                    pager = document.createElement('div');
                    pager.className = 'pagination';
                    pager.innerHTML = '<button type="button" class="btn ghost small"></button><span class="soft small"></span><button type="button" class="btn ghost small"></button>';
                    pager.children[0].textContent = {{ Js::from(__('Précédent')) }};
                    pager.children[2].textContent = {{ Js::from(__('Suivant')) }};
                    pager.children[0].addEventListener('click', function () { showPage(page - 1); });
                    pager.children[2].addEventListener('click', function () { showPage(page + 1); });
                    (isTable ? container.closest('.table-wrap') : container).after(pager);
                }

                function choose(value) {
                    apply(value);
                    remember(name, select.value);
                }

                select.addEventListener('change', function () { choose(select.value); });

                // Un clic trie, le deuxième inverse, le troisième revient à l'ordre habituel.
                heads.forEach(function (th, index) {
                    var label = fields[index].label;
                    var button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'sort-btn';
                    button.textContent = label;
                    button.title = {{ Js::from(__('Trier par ')) }} + label.toLowerCase();
                    th.textContent = '';
                    th.appendChild(button);
                    button.addEventListener('click', function () {
                        var first = firstDirection[fields[index].kind];
                        var second = first === 'asc' ? 'desc' : 'asc';
                        var current = select.value;
                        choose(current === index + ':' + first ? index + ':' + second : current === index + ':' + second ? '' : index + ':' + first);
                    });
                });

                apply(remembered(name));
            });
        })();

        // Sections repliées : chacune reste ouverte sur cet appareil, et un lien vers elle l'ouvre.
        (function () {
            var folds = document.querySelectorAll('.dash-fold');

            function store(key, value) {
                try {
                    if (value) { localStorage.setItem(key, '1'); } else { localStorage.removeItem(key); }
                } catch (error) {}
            }

            function openFromHash() {
                var target = location.hash && document.querySelector('.dash-fold' + location.hash);
                if (target) {
                    target.open = true;
                    target.scrollIntoView();
                }
            }

            folds.forEach(function (fold) {
                var key = 'dash-open-' + fold.id;
                try { fold.open = localStorage.getItem(key) === '1'; } catch (error) {}
                fold.addEventListener('toggle', function () { store(key, fold.open); });
            });

            openFromHash();
            window.addEventListener('hashchange', openFromHash);
        })();
    </script>
@endpush
