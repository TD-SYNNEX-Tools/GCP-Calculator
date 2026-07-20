<?php
declare(strict_types=1);

namespace App\Services;

enum PricingType: string
{
    case STANDARD     = 'STANDARD';
    case NON_STANDARD = 'NON_STANDARD';

    public function label(): string
    {
        return match ($this) {
            self::STANDARD     => 'Standard',
            self::NON_STANDARD => 'Non-Standard',
        };
    }
}
