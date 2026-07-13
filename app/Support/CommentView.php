<?php

namespace App\Support;

class CommentView
{
    public static function authorInitial(string $name): string
    {
        $name = trim($name);
        $anonymous = mb_strtolower(SiteConfig::str('comments_label_anonymous'));

        if ($name === '' || mb_strtolower($name) === $anonymous) {
            return '?';
        }

        return mb_strtoupper(mb_substr($name, 0, 1));
    }

    public static function authorHue(string $name): int
    {
        $hash = 0;
        $length = mb_strlen($name);

        for ($i = 0; $i < $length; $i++) {
            $hash = mb_ord(mb_substr($name, $i, 1)) + (($hash << 5) - $hash);
        }

        return abs($hash) % 360;
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    public static function countComments(array $items): int
    {
        $total = 0;
        foreach ($items as $item) {
            $total++;
            $children = $item['children'] ?? [];
            if (is_array($children) && $children !== []) {
                $total += self::countComments($children);
            }
        }

        return $total;
    }

    public static function countLabel(int $count): string
    {
        if ($count <= 0) {
            return '';
        }

        $mod10 = $count % 10;
        $mod100 = $count % 100;

        if ($mod100 >= 11 && $mod100 <= 14) {
            return $count . ' комментариев';
        }

        if ($mod10 === 1) {
            return $count . ' комментарий';
        }

        if ($mod10 >= 2 && $mod10 <= 4) {
            return $count . ' комментария';
        }

        return $count . ' комментариев';
    }

    /**
     * @param array<string, mixed> $comment
     * @return array<string, mixed>
     */
    public static function mapForTpl(array $comment, int $depth = 0, string $childrenHtml = ''): array
    {
        $author = (string)($comment['author'] ?? '');
        $userVote = $comment['user_vote'] ?? null;

        return [
            'id' => $comment['id'],
            'author' => $author,
            'created_at' => (string)($comment['created_at'] ?? ''),
            'body_html' => CommentBody::renderHtml((string)($comment['body'] ?? '')),
            'likes' => (int)($comment['likes'] ?? 0),
            'dislikes' => (int)($comment['dislikes'] ?? 0),
            'is_pinned' => !empty($comment['is_pinned']),
            'is_reply' => $depth > 0,
            'avatar_initial' => self::authorInitial($author),
            'avatar_hue' => self::authorHue($author),
            'user_vote_like' => $userVote === 1,
            'user_vote_dislike' => $userVote === -1,
            'children_html' => $childrenHtml,
        ];
    }
}
