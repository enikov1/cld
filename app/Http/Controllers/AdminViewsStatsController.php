<?php

namespace App\Http\Controllers;

use App\Services\AdminViewsStatsService;
use Illuminate\Http\Request;

class AdminViewsStatsController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'period' => ['nullable', 'in:today,yesterday,7d,30d,90d,365d,all,custom'],
            'group' => ['nullable', 'in:day,week,month'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'top' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return response()->json(AdminViewsStatsService::report(
            $data['period'] ?? '30d',
            $data['group'] ?? 'day',
            $data['date_from'] ?? null,
            $data['date_to'] ?? null,
            (int) ($data['top'] ?? 20),
        ));
    }

    public function summary()
    {
        return response()->json(AdminViewsStatsService::dashboardSnapshot());
    }
}
