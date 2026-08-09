<?php

namespace App\Services;

use App\Support\SiteConfig;

class KinoPoiskStaffMapper
{
    /**
     * @param array<int,array<string,mixed>> $staff
     * @return array{_actor_people: list<array{name: string, photo_url: string|null}>, _director_people: list<array{name: string, photo_url: string|null}>}
     */
    public static function toPeopleLists(array $staff): array
    {
        $actors = [];
        $directors = [];
        $maxActors = SiteConfig::int('import_max_actors');
        $maxDirectors = SiteConfig::int('import_max_directors');

        foreach ($staff as $member) {
            if (!is_array($member)) {
                continue;
            }

            $profession = strtoupper((string)($member['professionKey'] ?? ''));
            $name = trim((string)($member['nameRu'] ?? $member['nameEn'] ?? ''));
            if ($name === '') {
                continue;
            }

            $photoUrl = isset($member['posterUrl']) && trim((string)$member['posterUrl']) !== ''
                ? trim((string)$member['posterUrl'])
                : null;

            $entry = ['name' => $name, 'photo_url' => $photoUrl];

            if ($profession === 'ACTOR') {
                if ($maxActors > 0 && count($actors) >= $maxActors) {
                    continue;
                }
                $actors[] = $entry;
            } elseif ($profession === 'DIRECTOR') {
                if ($maxDirectors > 0 && count($directors) >= $maxDirectors) {
                    continue;
                }
                $directors[] = $entry;
            }
        }

        return [
            '_actor_people' => $actors,
            '_director_people' => $directors,
        ];
    }
}
