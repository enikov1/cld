<?php

use App\Http\Controllers\PublicStorageController;
use App\Http\Controllers\ThemeAssetController;
use Illuminate\Support\Facades\Route;

Route::get('/media/{path}', [PublicStorageController::class, 'show'])
    ->where('path', '.+')
    ->name('public.media');

Route::get('/theme-assets/{theme}/{path}', [ThemeAssetController::class, 'show'])
    ->where('theme', '[a-zA-Z0-9_-]+')
    ->where('path', '.+')
    ->name('theme.asset');
