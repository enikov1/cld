<?php

return [
    'segments' => [
        'home' => [
            'label' => 'Главная',
            'route' => 'home',
        ],
        'collections' => [
            'label' => 'Подборки',
            'route' => 'collections.index',
        ],
        'collection' => [
            'label' => null,
            'route' => 'collections.show',
            'params' => ['slug'],
        ],
        'studios' => [
            'label' => 'Студии',
            'route' => 'studios.index',
        ],
        'studio' => [
            'label' => null,
            'route' => 'studios.show',
            'params' => ['slug'],
        ],
        'category' => [
            'label' => null,
            'route' => 'home',
        ],
        'series' => [
            'label' => null,
            'route' => 'series.show',
            'params' => ['seriesPath'],
        ],
        'search' => [
            'label' => 'Поиск',
            'route' => 'search',
        ],
        'profile' => [
            'label' => 'Профиль',
            'route' => 'profile.show',
        ],
        'favourites' => [
            'label' => 'Избранное',
            'route' => 'favourites.show',
        ],
        'taxonomy_genre' => [
            'label' => null,
            'route' => 'taxonomy.genre.show',
            'params' => ['slug'],
        ],
        'taxonomy_country' => [
            'label' => null,
            'route' => 'taxonomy.country.show',
            'params' => ['slug'],
        ],
        'taxonomy_person' => [
            'label' => null,
            'route' => 'taxonomy.person.show',
            'params' => ['slug'],
        ],
        'taxonomy_year' => [
            'label' => null,
            'route' => 'taxonomy.year.show',
            'params' => ['slug'],
        ],
        'coming_soon' => [
            'label' => 'Скоро',
            'route' => 'coming_soon.index',
        ],
        'calendar' => [
            'label' => 'Календарь',
            'route' => 'calendar.index',
        ],
        'catalog' => [
            'label' => 'Каталог',
            'route' => 'catalog.index',
        ],
    ],

    'separator' => ' » ',
];
