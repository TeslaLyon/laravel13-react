<?php

namespace App\Filament\Resources\VideoResource\RelationManagers;

use App\Enums\VideoDownloadFeedbackStatus;
use App\Enums\VideoDownloadFeedbackType;
use App\Models\VideoDownloadFeedback;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DownloadFeedbacksRelationManager extends RelationManager
{
    protected static string $relationship = 'downloadFeedbacks';

    protected static ?string $title = '下载/磁力纠错反馈';

    protected static ?string $modelLabel = '纠错反馈';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('status')
                    ->label('处理状态')
                    ->options([
                        VideoDownloadFeedbackStatus::PENDING->value  => '待处理',
                        VideoDownloadFeedbackStatus::RESOLVED->value => '已解决',
                        VideoDownloadFeedbackStatus::IGNORED->value  => '已忽略',
                    ])
                    ->required(),
                Forms\Components\Textarea::make('admin_notes')
                    ->label('管理员备注')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),

                Tables\Columns\TextColumn::make('download.title')
                    ->label('报错资源')
                    ->limit(30)
                    ->description(fn (VideoDownloadFeedback $record) => $record->download ? "{$record->download->type} / " . ($record->download->resolution ?: '未标注') . ($record->download->status === 1 ? '' : ' 【已下架】') : '资源已删除'),

                Tables\Columns\TextColumn::make('type')
                    ->label('反馈分类')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof VideoDownloadFeedbackType ? $state->label() : VideoDownloadFeedbackType::tryFrom((int)$state)?->label() ?? '未知')
                    ->color(fn ($state) => match ($state instanceof VideoDownloadFeedbackType ? $state : VideoDownloadFeedbackType::tryFrom((int)$state)) {
                        VideoDownloadFeedbackType::DEAD_LINK,
                        VideoDownloadFeedbackType::PASSWORD_ERROR,
                        VideoDownloadFeedbackType::MALICIOUS_OR_AD => 'danger',
                        VideoDownloadFeedbackType::CONTENT_MISMATCH,
                        VideoDownloadFeedbackType::RESOLUTION_MISMATCH => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('content')
                    ->label('反馈说明')
                    ->limit(30)
                    ->tooltip(fn (VideoDownloadFeedback $record) => $record->content ?: '无详细说明'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('反馈人')
                    ->default('匿名访客')
                    ->formatStateUsing(fn (?string $state, VideoDownloadFeedback $record) => $record->user ? ($record->user->nickname ?: $record->user->name) : '匿名访客'),

                Tables\Columns\TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof VideoDownloadFeedbackStatus ? $state->label() : VideoDownloadFeedbackStatus::tryFrom((int)$state)?->label() ?? '未知')
                    ->color(fn ($state) => match ($state instanceof VideoDownloadFeedbackStatus ? $state : VideoDownloadFeedbackStatus::tryFrom((int)$state)) {
                        VideoDownloadFeedbackStatus::PENDING => 'warning',
                        VideoDownloadFeedbackStatus::RESOLVED => 'success',
                        VideoDownloadFeedbackStatus::IGNORED => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('admin_notes')
                    ->label('处理备注')
                    ->limit(20)
                    ->default('-'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('反馈时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('状态')
                    ->options([
                        VideoDownloadFeedbackStatus::PENDING->value  => '待处理',
                        VideoDownloadFeedbackStatus::RESOLVED->value => '已解决',
                        VideoDownloadFeedbackStatus::IGNORED->value  => '已忽略',
                    ]),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('handle')
                    ->label('处理')
                    ->icon('heroicon-o-check')
                    ->color('primary')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label('状态')
                            ->options([
                                VideoDownloadFeedbackStatus::RESOLVED->value => '已解决',
                                VideoDownloadFeedbackStatus::IGNORED->value  => '已忽略',
                            ])
                            ->default(VideoDownloadFeedbackStatus::RESOLVED->value)
                            ->required(),

                        Forms\Components\Checkbox::make('disable_download')
                            ->label('同时下架该下载资源')
                            ->default(false),

                        Forms\Components\Textarea::make('admin_notes')
                            ->label('处理备注')
                            ->rows(2),
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
                            ->title('反馈处理完成')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}

