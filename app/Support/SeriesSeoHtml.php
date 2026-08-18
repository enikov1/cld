<?php

namespace App\Support;

class SeriesSeoHtml
{
    public static function render(string $html): string
    {
        $html = trim(str_replace("\r\n", "\n", $html));
        if ($html === '') {
            return '';
        }

        $label = htmlspecialchars(SiteConfig::str('comments_ui_spoiler_reveal'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return preg_replace_callback(
            '/\[spoiler\]([\s\S]*?)\[\/spoiler\]/iu',
            static function (array $matches) use ($label): string {
                $content = $matches[1];

                return '<span class="comment-spoiler">'
                    . '<button type="button" class="comment-spoiler__toggle dontusebuttonclass" aria-expanded="false">'
                    . $label
                    . '</button>'
                    . '<span class="comment-spoiler__text" hidden>'
                    . $content
                    . '</span>'
                    . '</span>';
            },
            $html,
        ) ?? $html;
    }
}
