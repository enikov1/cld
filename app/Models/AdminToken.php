<?php

namespace App\Models;

use App\Support\AdminPermissions;
use Illuminate\Database\Eloquent\Model;

class AdminToken extends Model
{
    public const ROLE_FULL = 'full';

    public const ROLE_CONTENT = 'content';

    public const ROLE_MODERATION = 'moderation';

    public const ROLE_CUSTOM = 'custom';

    public const ROLES = [
        self::ROLE_FULL,
        self::ROLE_CONTENT,
        self::ROLE_MODERATION,
        self::ROLE_CUSTOM,
    ];

    protected $fillable = [
        'name',
        'token_hash',
        'role',
        'abilities',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'abilities' => 'array',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'token_hash',
    ];

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function generatePlaintext(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function touchLastUsed(): void
    {
        $this->forceFill(['last_used_at' => now()])->saveQuietly();
    }

    /**
     * @return list<string>
     */
    public function resolvedAbilities(): array
    {
        return AdminPermissions::normalizeAbilities(
            is_array($this->abilities) ? $this->abilities : null,
            (string) $this->role,
        );
    }

    /**
     * @return array{id: int, name: string, role: string, abilities: list<string>, is_active: bool, last_used_at: mixed, created_at: mixed, updated_at: mixed}
     */
    public function toAdminArray(): array
    {
        $abilities = $this->resolvedAbilities();

        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'role' => AdminPermissions::inferRole($abilities),
            'abilities' => $abilities,
            'is_active' => (bool) $this->is_active,
            'last_used_at' => $this->last_used_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
