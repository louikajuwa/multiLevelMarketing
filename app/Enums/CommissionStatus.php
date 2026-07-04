<?php

namespace App\Enums;

enum CommissionStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Void = 'void';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
