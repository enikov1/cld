<?php

namespace App\Services;

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
                $actors[] = $entry;
            } elseif ($profession === 'DIRECTOR') {
                $directors[] = $entry;
            }
        }

        return [
            '_actor_people' => $actors,
            '_director_people' => $directors,
        ];
    }
}
