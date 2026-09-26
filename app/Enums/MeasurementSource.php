<?php

declare(strict_types=1);

namespace App\Enums;

enum MeasurementSource: string
{
    case MANUAL = 'MANUAL';
    case IMPORT_EPPBGM = 'IMPORT_EPPBGM';

    public function label(): string
    {
        return match ($this) {
            self::MANUAL => 'Input Manual',
            self::IMPORT_EPPBGM => 'Import E-PPBGM',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::MANUAL => 'text-bg-light border',
            self::IMPORT_EPPBGM => 'text-bg-info',
        };
    }
}
