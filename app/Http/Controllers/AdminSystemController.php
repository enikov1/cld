<?php

namespace App\Http\Controllers;

use App\Support\AdminSystemStats;
use Illuminate\Http\JsonResponse;

class AdminSystemController extends Controller
{
    public function info(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'system' => AdminSystemStats::collect(),
        ]);
    }
}
