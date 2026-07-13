<?php

namespace App\Support;

use App\Models\User;
use App\Models\Watchlist;
use Illuminate\Support\Str;

class WatchlistDefaults
{
    /**
     * @return list<array{key: string, name: string, sort: int}>
     */
    public static function systemLists(): array
    {
        return [
            ['key' => 'watching', 'name' => 'Смотрю', 'sort' => 10],
            ['key' => 'will-watch', 'name' => 'Буду смотреть', 'sort' => 20],
            ['key' => 'seen', 'name' => 'Просмотрено', 'sort' => 30],
            ['key' => 'favourite', 'name' => 'Избранное', 'sort' => 40],
        ];
    }

    public static function ensureForUser(User|int $user): void
    {
        $userId = $user instanceof User ? $user->id : $user;

        foreach (self::systemLists() as $row) {
            Watchlist::query()->firstOrCreate(
                ['user_id' => $userId, 'system_key' => $row['key']],
                [
                    'name' => $row['name'],
                    'slug' => $row['key'],
                    'is_system' => true,
                    'sort_order' => $row['sort'],
                ]
            );
        }
    }

    public static function uniqueSlug(int $userId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'list';
        }

        $slug = $base;
        $i = 2;

        while (Watchlist::query()
            ->where('user_id', $userId)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }
}
