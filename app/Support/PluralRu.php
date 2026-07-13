<?php

namespace App\Support;

class PluralRu
{
    public static function series(int $count): string
    {
        $mod10 = $count % 10;
        $mod100 = $count % 100;

        if ($mod100 >= 11 && $mod100 <= 14) {
            return 'сериалов';
        }

        if ($mod10 === 1) {
            return 'сериал';
        }

        if ($mod10 >= 2 && $mod10 <= 4) {
            return 'сериала';
        }

        return 'сериалов';
    }

    public static function studios(int $count): string
    {
        $mod10 = $count % 10;
        $mod100 = $count % 100;

        if ($mod100 >= 11 && $mod100 <= 14) {
            return 'студий';
        }

        if ($mod10 === 1) {
            return 'студия';
        }

        if ($mod10 >= 2 && $mod10 <= 4) {
            return 'студии';
        }

        return 'студий';
    }

    public static function votes(int $count): string
    {
        $mod10 = $count % 10;
        $mod100 = $count % 100;

        if ($mod100 >= 11 && $mod100 <= 14) {
            return 'голосов';
        }

        if ($mod10 === 1) {
            return 'голос';
        }

        if ($mod10 >= 2 && $mod10 <= 4) {
            return 'голоса';
        }

        return 'голосов';
    }
}
