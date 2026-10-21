<?php

namespace App\Filament\Resources\VideoDownloadFeedbackResource\Pages;

use App\Enums\VideoDownloadFeedbackStatus;
use App\Filament\Resources\VideoDownloadFeedbackResource;
use App\Models\VideoDownloadFeedback;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListVideoDownloadFeedbacks extends ListRecords
{
    protected static string $resource = VideoDownloadFeedbackResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        $pendingCount = VideoDownloadFeedback::where('status', VideoDownloadFeedbackStatus::PENDING)->count();

        return [
            'all' => Tab::make('全部'),
            'pending' => Tab::make('待处理')
                ->badge($pendingCount > 0 ? (string)$pendingCount : null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', VideoDownloadFeedbackStatus::PENDING)),
            'resolved' => Tab::make('已解决')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', VideoDownloadFeedbackStatus::RESOLVED)),
            'ignored' => Tab::make('已忽略')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', VideoDownloadFeedbackStatus::IGNORED)),
        ];
    }
}

