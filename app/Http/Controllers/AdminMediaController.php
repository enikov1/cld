<?php

namespace App\Http\Controllers;

use App\Services\ImageOptimizer;
use App\Services\MediaLibraryService;
use App\Support\AdminAudit;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AdminMediaController extends Controller
{
    public function index(Request $request, MediaLibraryService $media)
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = (int) $request->query('per_page', 48);
        $q = trim((string) $request->query('q', ''));
        $type = trim((string) $request->query('type', ''));

        return response()->json($media->list(
            $page,
            $perPage,
            $q,
            $type !== '' ? $type : null,
        ));
    }

    public function upload(Request $request, MediaLibraryService $media, ImageOptimizer $optimizer)
    {
        $maxKb = (int) ceil($optimizer->maxUploadBytes() / 1024);

        $request->validate([
            'file' => ['required', 'file', 'image', 'max:' . $maxKb],
        ]);

        try {
            $item = $media->upload($request->file('file'));
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'item' => $item,
            'url' => $item['url'],
            'path' => $item['path'],
        ]);
    }

    public function destroy(Request $request, MediaLibraryService $media)
    {
        $path = trim((string) $request->query('path', $request->input('path', '')));
        if ($path === '') {
            return response()->json(['error' => 'Укажите path'], 422);
        }

        try {
            $deleted = $media->delete($path);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        if (!$deleted) {
            return response()->json(['error' => 'Файл не найден'], 404);
        }

        AdminAudit::log('media.delete', 'media', $path, 'Удалён файл медиатеки', ['path' => $path], $request);

        return response()->json(['ok' => true]);
    }
}
