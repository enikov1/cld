<?php

namespace App\Http\Controllers;

use App\Support\PublicMedia;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicStorageController extends Controller
{
    private const MIME = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
    ];

    public function show(string $path): BinaryFileResponse|Response
    {
        $resolved = PublicMedia::resolveDiskPath($path);
        if ($resolved === null) {
            return response('', 404);
        }

        $ext = strtolower(pathinfo($resolved, PATHINFO_EXTENSION));

        return response()->file($resolved, [
            'Content-Type' => self::MIME[$ext] ?? 'application/octet-stream',
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }
}
