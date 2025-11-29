<?php

declare(strict_types=1);

namespace Michalsn\CodeIgniterRelations\Enums;

enum OrderTypes: string
{
    case MIN = 'MIN';
    case MAX = 'MAX';

    /**
     * Get the ORDER BY direction for this order type
     *
     * @return string 'asc' for MIN, 'desc' for MAX
     */
    public function direction(): string
    {
        return match ($this) {
            self::MIN => 'asc',
            self::MAX => 'desc',
        };
    }
}
