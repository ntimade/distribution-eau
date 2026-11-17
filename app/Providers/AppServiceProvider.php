<?php

namespace App\Providers;

use App\Http\Controllers\InvoiceMessageController;
use App\Models\Reading;
use App\Services\CutoffNoticeService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Route::resourceVerbs(['create' => 'nouveau', 'edit' => 'modifier']);
        Paginator::defaultView('pagination.app');
        Paginator::defaultSimpleView('pagination.app');
        View::composer('layouts.app', function ($view) {
            $user = auth()->user();

            // Un releveur ne voit que ce qui reste à envoyer dans sa zone.
            if ($user?->isReader()) {
                $view->with('unpaidCount', 0)->with('messagesCount', Reading::real()->unpaid()->accessibleBy($user)
                    ->whereNull('whatsapp_opened_at')->whereNull('sms_opened_at')->count());

                return;
            }

            $view->with('unpaidCount', Reading::unpaid()->count())
                ->with('messagesCount', InvoiceMessageController::waitingCount(app(CutoffNoticeService::class)));
        });
    }
}
