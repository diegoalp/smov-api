<?php

namespace App\Enums;

enum UserType: string
{
    case Master = 'master';
    case Admin = 'admin';
    case Seller = 'seller';

    public function label(): string
    {
        return match ($this) {
            self::Master => 'Master',
            self::Admin => 'Administrador',
            self::Seller => 'Vendedor',
        };
    }
}
