<?php

namespace App\Http\Controllers;

use App\Services\History\ReliabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BiasHistoryController extends Controller
{
    public function show(Request $request, ReliabilityService $history): JsonResponse
    {
        $validated = $request->validate([
            'range' => ['sometimes', 'string', 'in:24h,7d,30d,90d,1y'],
            'scope' => ['sometimes', 'string', 'in:overall,5m,15m,1h,4h,1d,1w,1mo'],
        ]);

        return response()->json($history->data($validated['range'] ?? '7d', $validated['scope'] ?? 'overall'));
    }
}
