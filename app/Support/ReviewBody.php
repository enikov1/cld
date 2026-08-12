<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class ReviewBody
{
    public static function assertValid(string $body): string
    {
        $normalized = CommentBody::normalize($body);

        if (CommentBody::containsLink($normalized)) {
            throw ValidationException::withMessages([
                'body' => SiteConfig::str('reviews_msg_links_forbidden'),
            ]);
        }

        $min = SiteConfig::int('reviews_body_min_length');
        if (mb_strlen(CommentBody::effectiveBody($normalized)) < $min) {
            throw ValidationException::withMessages([
                'body' => SiteConfig::str('reviews_msg_too_short'),
            ]);
        }

        return $normalized;
    }

    public static function renderHtml(string $body): string
    {
        return CommentBody::renderHtml($body, 'reviews_ui_spoiler_reveal');
    }
}
