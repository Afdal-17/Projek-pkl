<?php

namespace App\Http\Controllers;

use App\Services\DashboardSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request, DashboardSummaryService $summaryService): View
    {
        return view('dashboard', [
            'summary' => $summaryService->forUser($request->user()),
            'wallets' => $request->user()->dompet()->latest()->get(),
        ]);
    }

    public function summary(Request $request, DashboardSummaryService $summaryService): JsonResponse
    {
        return response()->json($summaryService->forUser($request->user()));
    }
}
