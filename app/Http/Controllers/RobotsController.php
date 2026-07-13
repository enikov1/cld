<?php

namespace App\Http\Controllers;

use App\Support\RobotsTxt;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function index(): Response
    {
        return response(RobotsTxt::content(), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }
}
