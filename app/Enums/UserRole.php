<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'ADMIN';
    case PETUGAS_GIZI = 'PETUGAS_GIZI';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrator',
            self::PETUGAS_GIZI => 'Petugas Gizi',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::ADMIN => 'text-bg-primary',
            self::PETUGAS_GIZI => 'text-bg-success',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
