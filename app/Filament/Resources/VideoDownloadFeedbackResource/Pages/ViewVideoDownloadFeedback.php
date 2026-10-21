<?php

namespace App\Filament\Resources\VideoDownloadFeedbackResource\Pages;

use App\Enums\VideoDownloadFeedbackStatus;
use App\Filament\Resources\VideoDownloadFeedbackResource;
use App\Models\VideoDownloadFeedback;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewVideoDownloadFeedback extends ViewRecord
{
    protected static string $resource = VideoDownloadFeedbackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('handleFeedback')
                ->label('处理此纠错')
                ->icon('heroicon-o-check-circle')
                ->color('primary')
                ->modalHeading('处理下载纠错反馈')
                ->modalSubmitActionLabel('保存处理结果')
                ->form([
                    Forms\Components\Select::make('status')
                        ->label('处理结论')
                        ->options([
                            VideoDownloadFeedbackStatus::RESOLVED->value => '已解决 (修复/下架/更正)',
                            VideoDownloadFeedbackStatus::IGNORED->value  => '已忽略 (测试正常/误报)',
                        ])
                        ->default(VideoDownloadFeedbackStatus::RESOLVED->value)
                        ->required(),

                    Forms\Components\Checkbox::make('disable_download')
                        ->label('同时将该下载资源设为下架/禁用')
                        ->helperText('勾选后，前台将不再向用户展示该条下载资源')
                        ->default(false),

                    Forms\Components\Textarea::make('admin_notes')
                        ->label('处理说明 / 备注')
                        ->placeholder('例如：已重新抓取并更新磁力链接 / 经测试做种正常，判定为误报...')
                        ->rows(3),
                ])
                ->action(function (VideoDownloadFeedback $record, array $data): void {
                    $record->update([
                        'status'      => $data['status'],
                        'admin_notes' => $data['admin_notes'] ?? null,
                    ]);

                    if (!empty($data['disable_download']) && $record->download) {
                        $record->download->update(['status' => 0]);
                    }

                    Notification::make()
                        ->title('纠错反馈处理完毕')
                        ->success()
                        ->send();
                }),

            Actions\DeleteAction::make(),
        ];
    }
}

