<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class CommentBody
{
    /**
     * @var list<string>
     */
    private const LINK_PATTERNS = [
        '/(?:https?|ftp):\/\/\S+/iu',
        '/\bwww\.\S+/iu',
        '/\bmailto:\S+/iu',
        '/\[(?:url|link)(?:=|\])/iu',
        '/<a\s[\s\S]*?>/iu',
        '/(?<![\w@\/])(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+(?:com|ru|net|org|info|biz|me|io|tv|cc|su|ua|by|kz|рф|ру)(?:\/[^\s]*)?/iu',
    ];

    public static function normalize(string $body): string
    {
        $body = str_replace("\r\n", "\n", trim($body));
        $body = strip_tags($body);
        $body = preg_replace('/\[spoiler\]/iu', '[spoiler]', $body) ?? $body;
        $body = preg_replace('/\[\/spoiler\]/iu', '[/spoiler]', $body) ?? $body;

        return $body;
    }

    public static function containsLink(string $body): bool
    {
        foreach (self::LINK_PATTERNS as $pattern) {
            if (preg_match($pattern, $body)) {
                return true;
            }
        }

        return false;
    }

    public static function effectiveBody(string $body): string
    {
        $body = self::normalize($body);
        $body = preg_replace('/\[spoiler\]([\s\S]*?)\[\/spoiler\]/iu', '$1', $body) ?? $body;

        return trim($body);
    }

    public static function assertValid(string $body): string
    {
        $normalized = self::normalize($body);

        if (self::containsLink($normalized)) {
            throw ValidationException::withMessages([
                'body' => SiteConfig::str('comments_msg_links_forbidden'),
            ]);
        }

        $min = SiteConfig::int('comments_body_min_length');
        if (mb_strlen(self::effectiveBody($normalized)) < $min) {
            throw ValidationException::withMessages([
                'body' => SiteConfig::str('comments_msg_too_short'),
            ]);
        }

        return $normalized;
    }

    public static function renderHtml(string $body): string
    {
        $body = self::normalize($body);
        $parts = preg_split(
            '/(\[spoiler\][\s\S]*?\[\/spoiler\])/iu',
            $body,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        if ($parts === false) {
            return self::escapeText($body);
        }

        $html = '';
        foreach ($parts as $part) {
            if (preg_match('/^\[spoiler\]([\s\S]*)\[\/spoiler\]$/iu', $part, $matches)) {
                $html .= self::renderSpoilerHtml($matches[1]);
                continue;
            }

            $html .= self::escapeText($part);
        }

        return $html;
    }

    private static function renderSpoilerHtml(string $content): string
    {
        $label = htmlspecialchars(SiteConfig::str('comments_ui_spoiler_reveal'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text = self::escapeText($content);

        return '<span class="comment-spoiler">'
            . '<button type="button" class="comment-spoiler__toggle dontusebuttonclass" aria-expanded="false">'
            . $label
            . '</button>'
            . '<span class="comment-spoiler__text" hidden>'
            . $text
            . '</span>'
            . '</span>';
    }

    private static function escapeText(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
