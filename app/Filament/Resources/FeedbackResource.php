<?php

namespace App\Filament\Resources;

use App\Enums\FeedbackStatus;
use App\Filament\Resources\FeedbackResource\Pages;
use App\Models\Channel;
use App\Models\Feedback;
use App\Models\Video;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FeedbackResource extends Resource
{
    protected static ?string $model = Feedback::class;

    protected static ?string $navigationGroup = '反馈与审核';

    protected static ?string $modelLabel = '用户反馈';

    protected static ?string $pluralModelLabel = '用户反馈';

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $count = Feedback::where('status', FeedbackStatus::PENDING)->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('反馈基本信息')
                    ->schema([
                        Forms\Components\Placeholder::make('feedbackable_info')
                            ->label('关联对象')
                            ->content(function (?Feedback $record): string {
                                if (!$record || !$record->feedbackable) {
                                    return '未关联具体实体';
                                }
                                $model = $record->feedbackable;
                                if ($model instanceof Video) {
                                    return "[视频 #{$model->id}] {$model->name} (编号: {$model->video_code})";
                                }
                                if ($model instanceof Channel) {
                                    return "[片商] {$model->name}";
                                }
                                return class_basename($model) . " #{$model->id}";
                            }),

                        Forms\Components\Placeholder::make('user_info')
                            ->label('提交用户')
                            ->content(fn (?Feedback $record) => $record?->user ? ($record->user->nickname ?: $record->user->name) : '匿名/访客'),

                        Forms\Components\TextInput::make('type')
                            ->label('反馈分类')
                            ->disabled(),

                        Forms\Components\Select::make('status')
                            ->label('处理状态')
                            ->options([
                                FeedbackStatus::PENDING->value    => '待处理',
                                FeedbackStatus::PROCESSING->value => '处理中',
                                FeedbackStatus::RESOLVED->value   => '已解决',
                            ])
                            ->required(),

                        Forms\Components\Textarea::make('content')
                            ->label('用户反馈详情')
                            ->rows(4)
                            ->disabled()
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('admin_reply')
                            ->label('管理员处理答复 / 备注')
                            ->rows(3)
                            ->placeholder('可填写对用户的处理说明、内部排查记录等...')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('反馈详情与处置')
                    ->schema([
                        Infolists\Components\TextEntry::make('id')
                            ->label('反馈 ID'),

                        Infolists\Components\TextEntry::make('type')
                            ->label('问题分类')
                            ->badge()
                            ->color('info'),

                        Infolists\Components\TextEntry::make('status')
                            ->label('处理状态')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state instanceof FeedbackStatus ? $state->label() : FeedbackStatus::tryFrom((int)$state)?->label() ?? '未知')
                            ->color(fn ($state) => match ($state instanceof FeedbackStatus ? $state : FeedbackStatus::tryFrom((int)$state)) {
                                FeedbackStatus::PENDING    => 'warning',
                                FeedbackStatus::PROCESSING => 'info',
                                FeedbackStatus::RESOLVED   => 'success',
                                default                    => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('提交时间')
                            ->dateTime('Y-m-d H:i:s'),

                        Infolists\Components\TextEntry::make('user.name')
                            ->label('反馈人')
                            ->default('匿名访客'),

                        Infolists\Components\TextEntry::make('target_display')
                            ->label('关联目标')
                            ->state(function (Feedback $record): string {
                                $target = $record->feedbackable;
                                if ($target instanceof Video) {
                                    return "[视频 #{$target->id}] {$target->name} ({$target->video_code})";
                                }
                                if ($target instanceof Channel) {
                                    return "[片商] {$target->name}";
                                }
                                return $target ? class_basename($target) . " #{$target->id}" : '-';
                            })
                            ->url(function (Feedback $record): ?string {
                                $target = $record->feedbackable;
                                if ($target instanceof Video) {
                                    return VideoResource::getUrl('view', ['record' => $target->id]);
                                }
                                return null;
                            }, true),

                        Infolists\Components\TextEntry::make('content')
                            ->label('用户反馈说明')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('admin_reply')
                            ->label('管理员处理说明/答复')
                            ->default('暂无处理记录')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('handler.name')
                            ->label('处理人')
                            ->default('-'),

                        Infolists\Components\TextEntry::make('handled_at')
                            ->label('处理时间')
                            ->dateTime('Y-m-d H:i:s')
                            ->default('-'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->width('70px'),

                Tables\Columns\TextColumn::make('target')
                    ->label('关联对象')
                    ->state(function (Feedback $record): string {
                        $target = $record->feedbackable;
                        if ($target instanceof Video) {
                            return "{$target->video_code} - " . mb_strimwidth($target->name, 0, 28, '...');
                        }
                        if ($target instanceof Channel) {
                            return "[片商] {$target->name}";
                        }
                        return $target ? class_basename($target) . " #{$target->id}" : '-';
                    })
                    ->description(fn (Feedback $record) => $record->feedbackable_type ? class_basename($record->feedbackable_type) : '')
                    ->url(function (Feedback $record): ?string {
                        $target = $record->feedbackable;
                        if ($target instanceof Video) {
                            return VideoResource::getUrl('view', ['record' => $target->id]);
                        }
                        return null;
                    }, true)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHasMorph('feedbackable', [Video::class], function (Builder $q) use ($search) {
                            $q->where('name', 'ILIKE', "%{$search}%")
                              ->orWhere('video_code', 'ILIKE', "%{$search}%");
                        });
                    }),

                Tables\Columns\TextColumn::make('type')
                    ->label('反馈分类')
                    ->badge()
                    ->color('info')
                    ->searchable(),

                Tables\Columns\TextColumn::make('content')
                    ->label('反馈内容')
                    ->limit(35)
                    ->tooltip(fn (Feedback $record): ?string => $record->content),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('提交人')
                    ->default('匿名访客')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('处理状态')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof FeedbackStatus ? $state->label() : FeedbackStatus::tryFrom((int)$state)?->label() ?? '未知')
                    ->color(fn ($state) => match ($state instanceof FeedbackStatus ? $state : FeedbackStatus::tryFrom((int)$state)) {
                        FeedbackStatus::PENDING    => 'warning',
                        FeedbackStatus::PROCESSING => 'info',
                        FeedbackStatus::RESOLVED   => 'success',
                        default                    => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('handler.name')
                    ->label('处理人')
                    ->default('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('提交时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('处理状态')
                    ->options([
                        FeedbackStatus::PENDING->value    => '待处理',
                        FeedbackStatus::PROCESSING->value => '处理中',
                        FeedbackStatus::RESOLVED->value   => '已解决',
                    ]),

                Tables\Filters\SelectFilter::make('type')
                    ->label('反馈分类')
                    ->options([
                        '剧照有误' => '剧照有误',
                        '播放失败' => '播放失败',
                        '画质模糊' => '画质模糊',
                        '信息错误' => '信息错误',
                        '字幕问题' => '字幕问题',
                        '其他问题' => '其他问题',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('handleFeedback')
                    ->label('处理反馈')
                    ->icon('heroicon-o-check-circle')
                    ->color('primary')
                    ->modalHeading('处理用户反馈')
                    ->modalSubmitActionLabel('保存处理结论')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label('处理结论')
                            ->options([
                                FeedbackStatus::PROCESSING->value => '处理中 (排查中)',
                                FeedbackStatus::RESOLVED->value   => '已解决 (修复完毕)',
                            ])
                            ->default(FeedbackStatus::RESOLVED->value)
                            ->required(),

                        Forms\Components\Textarea::make('admin_reply')
                            ->label('答复 / 处理说明')
                            ->placeholder('例如：已重新同步最新剧照相册 / 经排查播放源已切换至可用 CDN...')
                            ->rows(3),
                    ])
                    ->action(function (Feedback $record, array $data): void {
                        $record->update([
                            'status'      => $data['status'],
                            'admin_reply' => $data['admin_reply'] ?? null,
                            'handled_by'  => auth()->id(),
                            'handled_at'  => now(),
                        ]);

                        Notification::make()
                            ->title('反馈状态更新成功')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('batchResolve')
                        ->label('批量标记为已解决')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->action(function ($records): void {
                            foreach ($records as $record) {
                                $record->update([
                                    'status'     => FeedbackStatus::RESOLVED,
                                    'handled_by' => auth()->id(),
                                    'handled_at' => now(),
                                ]);
                            }
                            Notification::make()
                                ->title('已批量标记为已解决')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFeedback::route('/'),
            'view'  => Pages\ViewFeedback::route('/{record}'),
        ];
    }
}

