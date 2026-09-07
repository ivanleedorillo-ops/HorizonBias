<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:dashboard-api')->get('/dashboard', [DashboardController::class, 'show']);
