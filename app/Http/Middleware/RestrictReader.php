<?php

namespace App\Http\Middleware;

use App\Models\Reading;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Un releveur ne peut que relever les index et envoyer les factures impayées de sa zone :
// tout ce qui n'est pas dans cette liste lui est refusé, y compris les futures pages.
class RestrictReader
{
    private const ALLOWED = [
        'readings.index', 'readings.store',
        'reader.invoices', 'messages.open', 'invoices.pdf',
        'account.edit', 'settings.password', 'settings.logo',
        'login', 'logout', 'locale.switch', 'theme.switch', 'csrf.refresh',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Le poste de support a ses propres comptes : on ne regarde l'utilisateur qu'en dehors.
        if ($request->is('/', 'support', 'support/*') || ! $request->user()?->isReader()) {
            return $next($request);
        }

        $user = $request->user();

        if ($request->routeIs('dashboard')) {
            return redirect()->route('readings.index');
        }

        abort_unless($request->routeIs(...self::ALLOWED), 403);

        $reading = $request->route('reading');
        if ($reading instanceof Reading) {
            abort_unless(Reading::accessibleBy($user)->whereKey($reading->id)->exists(), 404);
        }

        return $next($request);
    }
}
