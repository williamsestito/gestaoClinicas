<?php

declare(strict_types=1);

namespace App\Enums;

enum SaleItemType: string
{
    case Service = 'service';
    case Product = 'product';
    case ServicePackage = 'service_package';

    public function label(): string
    {
        return match ($this) {
            self::Service => 'Serviço',
            self::Product => 'Produto',
            self::ServicePackage => 'Pacote de sessões',
        };
    }
}
