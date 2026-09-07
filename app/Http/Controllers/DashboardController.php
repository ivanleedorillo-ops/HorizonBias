<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(DashboardService $dashboard): View
    {
        return view('dashboard', ['dashboard' => $dashboard->data()]);
    }

    public function show(DashboardService $dashboard): JsonResponse
    {
        return response()->json($dashboard->data());
    }
}
