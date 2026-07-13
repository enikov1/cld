<?php

namespace App\Support;

use App\Models\SiteSetting;

class CommentModeration
{
    public const SETTING_KEY = 'comments_auto_approve';

    public static function initialStatus(): string
    {
        return SiteSetting::get(self::SETTING_KEY, '0') === '1' ? 'approved' : 'pending';
    }

    public static function autoApproveEnabled(): bool
    {
        return self::initialStatus() === 'approved';
    }
}
