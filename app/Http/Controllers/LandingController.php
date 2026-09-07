<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class LandingController extends Controller
{
    public function index(): View
    {
        $timeframes = config('horizon.timeframes', []);
        $symbol = config('horizon.symbol', 'XAU/USD');

        return view('landing', [
            'symbol' => $symbol,
            'timeframes' => $timeframes,
        ]);
    }
}
