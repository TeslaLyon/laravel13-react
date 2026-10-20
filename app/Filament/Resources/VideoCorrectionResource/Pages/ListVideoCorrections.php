<?php

namespace App\Filament\Resources\VideoCorrectionResource\Pages;

use App\Filament\Resources\VideoCorrectionResource;
use Filament\Resources\Pages\ListRecords;

class ListVideoCorrections extends ListRecords
{
    protected static string $resource = VideoCorrectionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

