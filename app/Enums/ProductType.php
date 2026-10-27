<?php

namespace App\Enums;

enum ProductType: string
{
    case MARKETPLACE = 'marketplace';
    case DIGITAL = 'digital';

    public function label(): string
    {
        return match ($this) {
            self::MARKETPLACE => 'Marketplace',
            self::DIGITAL => 'Produk Digital',
        };
    }
}
