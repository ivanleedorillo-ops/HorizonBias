<?php

use App\Http\Controllers\BiasReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('landing');
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/reports/current/{format}', BiasReportController::class)
    ->whereIn('format', ['print', 'pdf', 'docx'])
    ->middleware('throttle:20,1')
    ->name('reports.current');
