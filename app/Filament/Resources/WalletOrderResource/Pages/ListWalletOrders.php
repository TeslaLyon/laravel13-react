<?php

namespace App\Filament\Resources\WalletOrderResource\Pages;

use App\Filament\Resources\WalletOrderResource;
use Filament\Resources\Pages\ListRecords;

class ListWalletOrders extends ListRecords
{
    protected static string $resource = WalletOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
