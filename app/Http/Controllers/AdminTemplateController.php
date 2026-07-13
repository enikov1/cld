<?php

namespace App\Http\Controllers;

use App\Support\ThemeManager;
use App\Support\ThemeTemplateService;
use App\Support\TplCache;
use App\Support\TplDocumentation;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AdminTemplateController extends Controller
{
    public function themes()
    {
        return response()->json([
            'items' => ThemeManager::listThemes(),
            'active' => ThemeManager::activeName(),
        ]);
    }

    public function tree(Request $request)
    {
        $theme = (string)$request->query('theme', ThemeManager::activeName());

        try {
            return response()->json([
                'theme' => $theme,
                'items' => ThemeTemplateService::listTree($theme),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function show(Request $request)
    {
        $data = $request->validate([
            'theme' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'path' => ['required', 'string', 'max:500'],
        ]);

        try {
            return response()->json(ThemeTemplateService::readFile($data['theme'], $data['path']));
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'theme' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'path' => ['required', 'string', 'max:500'],
            'content' => ['required', 'string'],
        ]);

        try {
            $result = ThemeTemplateService::writeFile($data['theme'], $data['path'], $data['content']);
            TplCache::bumpGlobalVersion();

            return response()->json(array_merge(['ok' => true], $result));
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function create(Request $request)
    {
        $data = $request->validate([
            'theme' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'path' => ['required', 'string', 'max:500'],
            'content' => ['nullable', 'string'],
        ]);

        try {
            $result = ThemeTemplateService::createFile(
                $data['theme'],
                $data['path'],
                (string)($data['content'] ?? '')
            );
            TplCache::bumpGlobalVersion();

            return response()->json(array_merge(['ok' => true], $result));
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function destroy(Request $request)
    {
        $data = $request->validate([
            'theme' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'path' => ['required', 'string', 'max:500'],
        ]);

        try {
            ThemeTemplateService::deleteEntry($data['theme'], $data['path']);
            TplCache::bumpGlobalVersion();

            return response()->json(['ok' => true]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function createDirectory(Request $request)
    {
        $data = $request->validate([
            'theme' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'path' => ['required', 'string', 'max:500'],
        ]);

        try {
            $result = ThemeTemplateService::createDirectory($data['theme'], $data['path']);

            return response()->json(array_merge(['ok' => true], $result));
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function rename(Request $request)
    {
        $data = $request->validate([
            'theme' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'from' => ['required', 'string', 'max:500'],
            'to' => ['required', 'string', 'max:500'],
        ]);

        try {
            $result = ThemeTemplateService::renameEntry($data['theme'], $data['from'], $data['to']);
            TplCache::bumpGlobalVersion();

            return response()->json(array_merge(['ok' => true], $result));
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function upload(Request $request)
    {
        $data = $request->validate([
            'theme' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'path' => ['required', 'string', 'max:500'],
            'file' => ['required', 'file', 'max:2048'],
        ]);

        try {
            $result = ThemeTemplateService::uploadFile($data['theme'], $data['path'], $request->file('file'));
            TplCache::bumpGlobalVersion();

            return response()->json(array_merge(['ok' => true], $result));
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function docs(Request $request)
    {
        $path = (string)$request->query('path', '');

        return response()->json(TplDocumentation::payloadForAdmin($path));
    }
}
