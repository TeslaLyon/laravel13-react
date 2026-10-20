<?php

namespace App\Filament\Resources\VideoCorrectionResource\Pages;

use App\Filament\Resources\VideoCorrectionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewVideoCorrection extends ViewRecord
{
    protected static string $resource = VideoCorrectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

