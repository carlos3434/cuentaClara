<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Yape = 'yape';
    case Plin = 'plin';
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case Other = 'other';

    /**
     * Methods an organizer can offer participants (excludes cash/other, which
     * are organizer-side or AI fallbacks).
     */
    public static function selectableValues(): array
    {
        return [self::Yape->value, self::Plin->value, self::BankTransfer->value];
    }
}
