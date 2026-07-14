<?php

/**
 * Реестр фильтров каталога.
 */
$ascDescSort = [
    'type' => 'select',
    'role' => 'sort',
    'source' => 'static',
    'default' => 'desc',
    'hide_empty' => true,
    'options' => [
        ['value' => 'desc', 'label' => 'По убыванию'],
        ['value' => 'asc', 'label' => 'По возрастанию'],
    ],
];

return [
    'layout' => [
        'genre',
        'country',
        'studio',
        'year_from',
        'year_to',
        'rating_min',
        'popularity_sort',
        'user_rating_sort',
        'views_sort',
        'comments_sort',
    ],

    'fields' => [
        'genre' => [
            'type' => 'select',
            'label' => 'Жанр',
            'empty' => 'Все жанры',
            'source' => 'taxonomy:genre',
        ],
        'country' => [
            'type' => 'select',
            'label' => 'Страна',
            'empty' => 'Все страны',
            'source' => 'taxonomy:country',
        ],
        'studio' => [
            'type' => 'select',
            'label' => 'Студия',
            'empty' => 'Все студии',
            'source' => 'studios',
        ],
        'year_from' => [
            'type' => 'select',
            'label' => 'Год от',
            'empty' => 'Любой',
            'source' => 'taxonomy:year',
        ],
        'year_to' => [
            'type' => 'select',
            'label' => 'Год до',
            'empty' => 'Любой',
            'source' => 'taxonomy:year',
        ],
        'rating_min' => [
            'type' => 'range',
            'label' => 'Рейтинг КП / IMDb',
            'min' => 0,
            'max' => 10,
            'step' => 0.5,
            'suffix' => '+',
        ],
        'popularity_sort' => array_merge($ascDescSort, [
            'label' => 'Популярность TMDB',
        ]),
        'user_rating_sort' => array_merge($ascDescSort, [
            'label' => 'Оценка пользователей',
        ]),
        'views_sort' => array_merge($ascDescSort, [
            'label' => 'Просмотры',
        ]),
        'comments_sort' => array_merge($ascDescSort, [
            'label' => 'Комментарии',
        ]),
    ],

    'partials' => [
        'select' => 'partials/catalog_filter_select.tpl',
        'range' => 'partials/catalog_filter_range.tpl',
        'number' => 'partials/catalog_filter_number.tpl',
    ],
];
