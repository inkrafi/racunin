<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case AFFILIATE = 'affiliate';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrator',
            self::AFFILIATE => 'Affiliate Marketer',
        };
    }

    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }

    public function isAffiliate(): bool
    {
        return $this === self::AFFILIATE;
    }
}
