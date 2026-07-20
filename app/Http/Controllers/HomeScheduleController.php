<?php

namespace App\Http\Controllers;

use App\Services\HomeEpisodeScheduleService;
use Illuminate\Http\Request;

class HomeScheduleController extends Controller
{
    public function calendar(Request $request)
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:1970', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        return response()->json(
            HomeEpisodeScheduleService::calendarMonth(
                (int) $validated['year'],
                (int) $validated['month'],
            )
        );
    }
}
