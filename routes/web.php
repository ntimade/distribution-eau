<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CutoffNoticeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceMessageController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\MeterController;
use App\Http\Controllers\MeterReplacementController;
use App\Http\Controllers\OpeningBalanceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReaderController;
use App\Http\Controllers\ReaderInvoiceController;
use App\Http\Controllers\ReadingController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\Support\AccountController as SupportAccountController;
use App\Http\Controllers\Support\RequestController as SupportRequestAdminController;
use App\Http\Controllers\Support\SessionController as SupportSessionController;
use App\Http\Controllers\Support\SubscriptionController as SupportSubscriptionController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\UnpaidController;
use App\Http\Controllers\ZoneController;
use App\Http\Middleware\AuthenticateSupport;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/tableau-de-bord');

Route::get('langue/{locale}', fn (string $locale) => back(fallback: '/')->withCookie(cookie()->forever('locale', $locale)))
    ->whereIn('locale', SetLocale::LOCALES)->name('locale.switch');

Route::get('theme/{theme}', fn (string $theme) => back(fallback: '/')->withCookie(cookie()->forever('theme', $theme)))
    ->whereIn('theme', ['light', 'dark'])->name('theme.switch');

Route::get('csrf/refresh', fn () => response()->json(['token' => csrf_token()]))->name('csrf.refresh');

require __DIR__.'/auth.php';

Route::middleware('auth')->group(function () {
    Route::get('tableau-de-bord', DashboardController::class)->name('dashboard');

    Route::post('clients/tarif', [CustomerController::class, 'bulkPrice'])->name('customers.bulk-price');
    Route::resource('clients', CustomerController::class)
        ->names('customers')->parameters(['clients' => 'customer']);
    Route::resource('compteurs', MeterController::class)
        ->names('meters')->parameters(['compteurs' => 'meter']);
    Route::get('compteurs/{meter}/remplacer', [MeterReplacementController::class, 'create'])->name('meters.replace');
    Route::post('compteurs/{meter}/remplacer', [MeterReplacementController::class, 'store'])->name('meters.replace.store');
    Route::get('remplacements/{replacement}/modifier', [MeterReplacementController::class, 'edit'])->name('meters.replacements.edit');
    Route::put('remplacements/{replacement}', [MeterReplacementController::class, 'update'])->name('meters.replacements.update');
    Route::delete('remplacements/{replacement}', [MeterReplacementController::class, 'destroy'])->name('meters.replacements.destroy');
    Route::resource('zones', ZoneController::class)->except(['create', 'show']);
    Route::resource('releves', ReadingController::class)
        ->names('readings')->parameters(['releves' => 'reading']);

    Route::get('factures', [InvoiceController::class, 'month'])->name('invoices.month');
    Route::get('messages-de-facture', [InvoiceMessageController::class, 'index'])->name('messages.index');
    Route::post('impayes/{reading}/rappel/{channel}', [InvoiceMessageController::class, 'openReminder'])->whereIn('channel', ['whatsapp', 'sms'])->name('messages.reminder');
    Route::post('paiements/{payment}/message/{channel}', [InvoiceMessageController::class, 'openPayment'])->whereIn('channel', ['whatsapp', 'sms'])->name('messages.payment');
    Route::post('factures/{reading}/message/{channel}', [InvoiceMessageController::class, 'open'])->whereIn('channel', ['whatsapp', 'sms'])->name('messages.open');
    Route::get('factures/{reading}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('factures/{reading}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');

    Route::get('impayes', [UnpaidController::class, 'index'])->name('unpaid.index');

    Route::get('dettes-anterieures', [OpeningBalanceController::class, 'index'])->name('opening-balances.index');
    Route::post('dettes-anterieures', [OpeningBalanceController::class, 'store'])->name('opening-balances.store');
    Route::get('dettes-anterieures/{reading}/modifier', [OpeningBalanceController::class, 'edit'])->name('opening-balances.edit');
    Route::put('dettes-anterieures/{reading}', [OpeningBalanceController::class, 'update'])->name('opening-balances.update');
    Route::delete('dettes-anterieures/{reading}', [OpeningBalanceController::class, 'destroy'])->name('opening-balances.destroy');

    Route::get('avis-de-coupure', [CutoffNoticeController::class, 'index'])->name('notices.index');
    Route::post('avis-de-coupure', [CutoffNoticeController::class, 'store'])->name('notices.store');
    Route::post('avis-de-coupure/envoyer/{channel}', [CutoffNoticeController::class, 'send'])->whereIn('channel', ['whatsapp', 'sms'])->name('notices.send');
    Route::post('avis-de-coupure/tous', [CutoffNoticeController::class, 'storeAll'])->name('notices.store-all');
    Route::get('avis-de-coupure/imprimer', [CutoffNoticeController::class, 'printAll'])->name('notices.print');
    Route::get('avis-de-coupure/{notice}', [CutoffNoticeController::class, 'show'])->name('notices.show');

    // Encaissement de plusieurs mois en une fois, sur un ou plusieurs compteurs d'un client.
    Route::get('encaissements', [ReceiptController::class, 'index'])->name('receipts.index');
    Route::get('encaissements/nouveau', [ReceiptController::class, 'create'])->name('receipts.create');
    Route::post('clients/{customer}/encaissements', [ReceiptController::class, 'store'])->name('receipts.store');
    Route::get('encaissements/{receipt}', [ReceiptController::class, 'show'])->whereNumber('receipt')->name('receipts.show');
    Route::get('encaissements/{receipt}/imprimer', [ReceiptController::class, 'print'])->whereNumber('receipt')->name('receipts.print');
    Route::get('encaissements/{receipt}/pdf', [ReceiptController::class, 'pdf'])->whereNumber('receipt')->name('receipts.pdf');
    Route::delete('encaissements/{receipt}', [ReceiptController::class, 'destroy'])->whereNumber('receipt')->name('receipts.destroy');
    Route::post('encaissements/{receipt}/message/{channel}', [ReceiptController::class, 'message'])->whereNumber('receipt')->whereIn('channel', ['whatsapp', 'sms'])->name('receipts.message');

    Route::get('paiements', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('releves/{reading}/paiements', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('paiements/{payment}/modifier', [PaymentController::class, 'edit'])->name('payments.edit');
    Route::put('paiements/{payment}', [PaymentController::class, 'update'])->name('payments.update');
    Route::delete('paiements/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');

    Route::get('carte', [MapController::class, 'index'])->name('map.index');

    Route::get('abonnement', [SubscriptionController::class, 'index'])->name('subscription.index');
    Route::post('abonnement', [SubscriptionController::class, 'store'])->middleware('throttle:10,1')->name('subscription.store');

    Route::get('factures-impayees', [ReaderInvoiceController::class, 'index'])->name('reader.invoices');
    Route::view('mon-mot-de-passe', 'account.password')->name('account.edit');
    Route::resource('releveurs', ReaderController::class)->except('show')
        ->names('readers')->parameters(['releveurs' => 'reader'])->whereNumber('reader');

    Route::get('parametres', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('parametres', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('parametres/logo', [SettingsController::class, 'showLogo'])->name('settings.logo');
    Route::put('parametres/echeance-mois', [SettingsController::class, 'updateMonthlyDue'])->name('settings.monthly-due');
    Route::delete('parametres/echeance-mois/{monthlyDueDate}', [SettingsController::class, 'destroyMonthlyDue'])->name('settings.monthly-due.destroy');
    Route::put('parametres/delais', [SettingsController::class, 'updateBillingDelays'])->name('settings.billing-delays');
    Route::post('parametres/logo',[SettingsController::class, 'updateLogo'])->name('settings.logo.update');
    Route::delete('parametres/logo', [SettingsController::class, 'destroyLogo'])->name('settings.logo.destroy');
    Route::put('parametres/frais-entretien',[SettingsController::class, 'updateMaintenance'])->name('settings.maintenance');
    Route::delete('parametres/frais-entretien/{maintenanceFee}', [SettingsController::class, 'destroyMaintenance'])->name('settings.maintenance.destroy');
    Route::put('parametres/mot-de-passe', [SettingsController::class, 'updatePassword'])->name('settings.password');
    Route::put('parametres/questions-securite', [SettingsController::class, 'updateSecurityQuestions'])->name('settings.security');
});

// Poste de support : comptes séparés de ceux des distributeurs.
Route::prefix('support')->name('support.')->group(function () {
    Route::get('/', fn () => redirect()->route(Auth::guard('support')->check() ? 'support.accounts.index' : 'support.login'))->name('home');

    Route::middleware('guest:support')->group(function () {
        Route::get('connexion', [SupportSessionController::class, 'create'])->name('login');
        Route::post('connexion', [SupportSessionController::class, 'store'])->middleware('throttle:10,1');
    });

    Route::middleware(AuthenticateSupport::class)->group(function () {
        Route::post('deconnexion', [SupportSessionController::class, 'destroy'])->name('logout');
        Route::get('demandes', [SupportRequestAdminController::class, 'index'])->name('requests.index');
        Route::get('demandes/{supportRequest}', [SupportRequestAdminController::class, 'show'])->name('requests.show');
        Route::post('demandes/{supportRequest}/reinitialiser', [SupportRequestAdminController::class, 'reset'])->name('requests.reset');
        Route::post('demandes/{supportRequest}/cloturer', [SupportRequestAdminController::class, 'close'])->name('requests.close');

        Route::get('comptes', [SupportAccountController::class, 'index'])->name('accounts.index');
        Route::get('comptes/{organisation}', [SupportAccountController::class, 'show'])->whereNumber('organisation')->name('accounts.show');
        Route::post('comptes/{organisation}/utilisateurs/{user}/reinitialiser', [SupportAccountController::class, 'resetPassword'])->whereNumber('user')->name('accounts.reset');
        Route::post('comptes/{organisation}/suspendre', [SupportAccountController::class, 'suspend'])->name('accounts.suspend');
        Route::post('comptes/{organisation}/reactiver', [SupportAccountController::class, 'reactivate'])->name('accounts.reactivate');
        Route::post('comptes/{organisation}/prolonger', [SupportSubscriptionController::class, 'extend'])->name('subscriptions.extend');

        Route::get('abonnements', [SupportSubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::get('abonnements/plans', [SupportSubscriptionController::class, 'settings'])->name('subscriptions.settings');
        Route::put('abonnements/plans', [SupportSubscriptionController::class, 'updatePlans'])->name('subscriptions.plans');
        Route::put('abonnements/modes', [SupportSubscriptionController::class, 'updateMethods'])->name('subscriptions.methods');
        Route::post('abonnements/{payment}/valider', [SupportSubscriptionController::class, 'approve'])->name('subscriptions.approve');
        Route::post('abonnements/{payment}/refuser', [SupportSubscriptionController::class, 'reject'])->name('subscriptions.reject');
    });
});
