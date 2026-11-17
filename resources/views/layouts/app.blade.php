<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="{{ ui_theme() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset_v('images/favicon.png') }}">
    @include('partials.installable-head')
    <link rel="stylesheet" href="{{ asset_v('css/app.css') }}">
    @stack('head')
    <link rel="stylesheet" href="{{ asset_v('css/theme.css') }}">
</head>
<body>
<div class="shell">
    <header class="topbar">
        <button type="button" class="icon-btn" data-nav-open aria-label="{{ __('Ouvrir le menu') }}" aria-expanded="false" aria-controls="sidebar">
            <x-icon name="menu" />
        </button>
        <a class="topbar-brand" href="{{ route('dashboard') }}">
            <x-org-logo />
            <span>{{ auth()->user()->organisation->name }}</span>
        </a>
        {{-- Sur téléphone, le menu latéral est replié : le bouton d'installation reste visible ici. Même script que celui du menu. --}}
        <button type="button" class="lang-switch topbar-install" data-install-app hidden aria-label="{{ __('Installer l\'application') }}" title="{{ __('Installer l\'application') }}">
            <x-icon name="download" />
        </button>
        <x-theme-switch />
        <x-lang-switch />
        {{-- Sur téléphone et tablette, la déconnexion reste visible en haut au lieu d'être cachée dans le tiroir. --}}
        <form class="topbar-logout" method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="lang-switch logout-switch" aria-label="{{ __('Déconnexion') }}" title="{{ __('Déconnexion') }}"><x-icon name="log-out" /></button>
        </form>
    </header>
    <div class="nav-overlay" data-nav-close></div>

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-head">
            <x-org-logo />
            <div class="brand-text">
                <span class="brand-name">{{ auth()->user()->organisation->name }}</span>
                <span class="brand-code">{{ __('Code : :v1', ['v1' => auth()->user()->organisation->code]) }}</span>
            </div>
            <button type="button" class="icon-btn sidebar-close" data-nav-close aria-label="{{ __('Fermer le menu') }}">
                <x-icon name="x" />
            </button>
        </div>

        <nav aria-label="{{ __('Menu principal') }}">
            @if (auth()->user()->organisation->isAccessLocked())
                {{-- Accès bloqué : seul le paiement reste proposé. --}}
                <a href="{{ route('subscription.index') }}" @class(['nav-link', 'active' => request()->routeIs('subscription.*')]) aria-current="page">
                    <x-icon name="crown" />
                    {{ __('Abonnement') }}
                </a>
            @elseif (auth()->user()->isReader())
                @foreach ([
                    ['readings.index', __('Relevés'), 'readings.*', 'clipboard-list'],
                    ['reader.invoices', __('Factures impayées'), 'reader.invoices', 'send'],
                    ['account.edit', __('Mon mot de passe'), 'account.edit', 'settings'],
                ] as [$route, $label, $pattern, $icon])
                    <a href="{{ route($route) }}" @class(['nav-link', 'active' => request()->routeIs($pattern)]) @if (request()->routeIs($pattern)) aria-current="page" @endif>
                        <x-icon :name="$icon" />
                        {{ $label }}
                        @if ($route === 'reader.invoices' && $messagesCount > 0)<span class="nav-count">{{ $messagesCount }}</span>@endif
                    </a>
                @endforeach
            @else
            @foreach ([
                '' => [
                    ['dashboard', __('Tableau de bord'), 'dashboard', 'layout-dashboard'],
                ],
                'Réseau' => [
                    ['customers.index', __('Clients'), 'customers.*', 'users'],
                    ['meters.index', __('Compteurs'), 'meters.*', 'gauge'],
                    ['zones.index', __('Zones'), 'zones.*', 'map-pin'],
                    ['map.index', __('Carte'), 'map.*', 'map'],
                ],
                'Facturation' => [
                    ['readings.index', __('Relevés'), 'readings.*', 'clipboard-list'],
                    ['messages.index', __('Messages aux clients'), 'messages.*', 'send'],
                    ['unpaid.index', __('Impayés'), 'unpaid.*', 'circle-alert'],
                    ['opening-balances.index', __('Dettes antérieures'), 'opening-balances.*', 'receipt'],
                    ['notices.index', __('Avis de coupure'), 'notices.*', 'power-off'],
                    ['receipts.index', __('Encaissements'), 'receipts.*', 'receipt'],
                    ['payments.index', __('Paiements'), 'payments.*', 'wallet'],
                ],
                'Compte' => [
                    ['subscription.index', __('Abonnement'), 'subscription.*', 'crown'],
                    ['readers.index', __('Releveurs'), 'readers.*', 'clipboard-list'],
                    ['settings.edit', __('Paramètres'), 'settings.*', 'settings'],
                ],
            ] as $section => $links)
                @if ($section)<p class="nav-title">{{ __($section) }}</p>@endif
                @foreach ($links as [$route, $label, $pattern, $icon])
                    <a href="{{ route($route) }}" @class(['nav-link', 'active' => request()->routeIs($pattern)]) @if (request()->routeIs($pattern)) aria-current="page" @endif>
                        <x-icon :name="$icon" />
                        {{ $label }}
                        @if ($route === 'messages.index' && $messagesCount > 0)
                            <span class="nav-count" title="{{ __(':v1 message(s) à envoyer', ['v1' => $messagesCount]) }}">{{ $messagesCount }}</span>
                        @endif
                        @if ($route === 'unpaid.index' && $unpaidCount > 0)
                            <span class="nav-count" title="{{ __(':v1 facture(s) impayée(s)', ['v1' => $unpaidCount]) }}">{{ $unpaidCount }}</span>
                        @endif
                    </a>
                @endforeach
            @endforeach
            @endif
        </nav>

        <x-install-app class="sidebar-install" />

        <div class="sidebar-foot">
            <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
            <div class="user-text">
                <strong>{{ auth()->user()->name }}</strong>
                <span>{{ auth()->user()->email }}</span>
            </div>
            <x-theme-switch />
    <x-lang-switch />
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="icon-btn" aria-label="{{ __('Déconnexion') }}" title="{{ __('Déconnexion') }}"><x-icon name="log-out" /></button>
            </form>
        </div>
    </aside>

    <nav @class(['bottom-nav', 'locked-nav' => auth()->user()->organisation->isAccessLocked(), 'reader-nav' => auth()->user()->isReader()]) aria-label="{{ __('Accès rapide') }}">
        @foreach (auth()->user()->organisation->isAccessLocked() ? [
            ['subscription.index', __('Abonnement'), 'subscription.*', 'crown'],
        ] : (auth()->user()->isReader() ? [
            ['readings.index', __('Relevés'), 'readings.*', 'clipboard-list'],
            ['reader.invoices', __('Factures'), 'reader.invoices', 'send'],
            ['account.edit', __('Mot de passe'), 'account.edit', 'settings'],
        ] : [
            ['dashboard', __('Accueil'), 'dashboard', 'layout-dashboard'],
            ['customers.index', __('Clients'), 'customers.*', 'users'],
            ['readings.index', __('Relevés'), 'readings.*', 'clipboard-list'],
            ['unpaid.index', __('Impayés'), 'unpaid.*', 'circle-alert'],
            ['map.index', __('Carte'), 'map.*', 'map'],
        ]) as [$route, $label, $pattern, $icon])
            <a href="{{ route($route) }}" @class(['active' => request()->routeIs($pattern)]) @if (request()->routeIs($pattern)) aria-current="page" @endif>
                <x-icon :name="$icon" />
                {{ $label }}
                @if ($route === 'unpaid.index' && $unpaidCount > 0)<span class="dot"></span>@endif
            </a>
        @endforeach
    </nav>

    <main class="content">
        <div class="content-inner">
            @if (session('success'))<div class="flash success">{{ session('success') }}</div>@endif
            @if (session('error'))<div class="flash error">{{ session('error') }}</div>@endif
            @unless (auth()->user()->isReader())
            @if (auth()->user()->organisation->price_per_m3 === null && ! request()->routeIs('settings.*') && ! auth()->user()->organisation->isAccessLocked())
                <div class="flash info">{{ __('Renseignez le prix du mètre cube pour pouvoir saisir des relevés.') }} <a href="{{ route('settings.edit') }}">{{ __('Aller aux paramètres') }}</a></div>
            @endif
            @if (! auth()->user()->securityAnswers()->exists() && ! request()->routeIs('settings.*') && ! auth()->user()->organisation->isAccessLocked())
                <div class="flash info">{{ __('Renseignez vos questions de sécurité') }} {{ __('pour pouvoir récupérer votre mot de passe en cas d\'oubli.') }} <a href="{{ route('settings.edit') }}#section-questions">{{ __('Aller aux paramètres') }}</a></div>
            @endif
            @php($subscriber = auth()->user()->organisation)
            @if ($subscriber->subscriptionState() === 'expired')
                <div class="flash error">{{ __('Votre abonnement a expiré le :v1. Effectuez le paiement pour retrouver les fonctionnalités.', ['v1' => $subscriber->subscribed_until->format('d/m/Y')]) }}</div>
            @elseif ($subscriber->subscriptionState() === 'ending')
                <div class="flash error">{{ __('Votre abonnement se termine le :v1. Sans paiement, le service prendra bientôt fin.', ['v1' => $subscriber->subscribed_until->format('d/m/Y')]) }} <a href="{{ route('subscription.index') }}">{{ __('Payer mon abonnement') }}</a></div>
            @elseif ($subscriber->subscriptionState() === 'trial')
                <div class="flash error">{{ __('Essai gratuit jusqu\'au :v1. Sans plan choisi à cette date, l\'accès sera suspendu.', ['v1' => $subscriber->trial_ends_at->format('d/m/Y')]) }} <a href="{{ route('subscription.index') }}">{{ __('Choisir un plan') }}</a></div>
            @elseif ($subscriber->isTrialLocked())
                <div class="flash error">{{ __('Votre essai gratuit est terminé et votre accès est suspendu. Choisissez un plan pour le retrouver : vos données sont conservées.') }}</div>
            @elseif ($subscriber->subscriptionState() === 'trial_over' && \App\Models\SubscriptionPlan::available()->exists())
                <div class="flash error">{{ __('Votre essai gratuit est terminé. Payez votre abonnement pour éviter la suspension de l\'accès.') }} <a href="{{ route('subscription.index') }}">{{ __('Voir ma demande') }}</a></div>
            @elseif ($subscriber->subscriptionState() === 'none' && \App\Models\SubscriptionPlan::available()->exists())
                <div class="flash error">{{ __('Votre abonnement n\'est pas payé. Sans paiement, le service prendra bientôt fin.') }} <a href="{{ route('subscription.index') }}">{{ __('Payer mon abonnement') }}</a></div>
            @endif
            @endunless
            @yield('content')
            <x-app-footer />
        </div>
    </main>
</div>
<script>
    window.locationText = {{ Js::from([
        'insecure' => __('Localisation indisponible : le site doit être ouvert en https.'),
        'unsupported' => __('Cet appareil ne permet pas la localisation.'),
        'denied' => __('Localisation refusée. Autorisez-la dans les réglages du navigateur pour ce site, puis réessayez.'),
        'unavailable' => __('Position introuvable. Activez la localisation (GPS) de votre appareil, puis réessayez.'),
        'timeout' => __('La localisation met trop de temps à répondre. Réessayez, si possible à l\'extérieur.'),
        'here' => __('Vous êtes ici'),
    ]) }};
</script>
@include('layouts.table-text')
<script src="{{ asset_v('js/app.js') }}" data-csrf-url="{{ route('csrf.refresh') }}"></script>
@stack('scripts')
</body>
</html>
