<?php

namespace App\Enums;

enum CommissionType: string
{
    case Direct = 'direct';
    case SponsorBonus = 'sponsor_bonus';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Direct => 'Direct commission',
            self::SponsorBonus => 'Sponsor bonus',
            self::Adjustment => 'Manual adjustment',
        };
    }
}
