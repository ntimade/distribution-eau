<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): View
    {
        $text = fn (string $key) => is_string($request->query($key)) ? $request->query($key) : null;

        return view('dashboard', $dashboard->build($text('vue'), $text('mois'), $text('annee')));
    }
}
