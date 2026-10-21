<?php

namespace App\Filament\Resources;

use App\Enums\VideoDownloadFeedbackStatus;
use App\Enums\VideoDownloadFeedbackType;
use App\Filament\Resources\VideoDownloadFeedbackResource\Pages;
use App\Models\Video;
use App\Models\VideoDownload;
use App\Models\VideoDownloadFeedback;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VideoDownloadFeedbackResource extends Resource
{
    protected static ?string $model = VideoDownloadFeedback::class;

    protected static ?string $navigationGroup = '反馈与审核';

    protected static ?string $modelLabel = '下载纠错';

    protected static ?string $pluralModelLabel = '下载纠错反馈';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $count = VideoDownloadFeedback::where('status', VideoDownloadFeedbackStatus::PENDING)->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('反馈基本信息')
                    ->schema([
                        Forms\Components\Placeholder::make('video_info')
                            ->label('关联视频')
                            ->content(function (?VideoDownloadFeedback $record) {
                                if (!$record?->video) return '-';
                                return "[#{$record->video->id}] {$record->video->name} ({$record->video->video_code})";
                            }),

                        Forms\Components\Placeholder::make('download_info')
                            ->label('报错下载资源')
                            ->content(function (?VideoDownloadFeedback $record) {
                                if (!$record?->download) return '资源已删除/不存在';
                                $dl = $record->download;
                                $statusStr = $dl->status === 1 ? '【有效】' : '【已下架】';
                                return "{$statusStr} {$dl->title} ({$dl->type} / " . ($dl->resolution ?: '未标注') . ")";
                            }),

                        Forms\Components\Placeholder::make('user_info')
                            ->label('提交用户')
                            ->content(fn (?VideoDownloadFeedback $record) => $record?->user ? ($record->user->nickname ?: $record->user->name) : '匿名访客'),

                        Forms\Components\Placeholder::make('type_display')
                            ->label('反馈分类')
                            ->content(fn (?VideoDownloadFeedback $record) => $record?->type ? $record->type->label() . ' - ' . $record->type->description() : '-'),

                        Forms\Components\Textarea::make('content')
                            ->label('用户反馈详情')
                            ->rows(3)
                            ->disabled()
                            ->columnSpanFull(),

                        Forms\Components\Select::make('status')
                            ->label('处理状态')
                            ->options(array_combine(
                                array_map(fn($case) => $case->value, VideoDownloadFeedbackStatus::cases()),
                                array_map(fn($case) => $case->label(), VideoDownloadFeedbackStatus::cases())
                            ))
                            ->required(),

                        Forms\Components\Textarea::make('admin_notes')
                            ->label('管理员处理备注')
                            ->rows(3)
                            ->placeholder('可填写排查结论、是否更换资源或处理原因...')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('纠错反馈概况')
                    ->schema([
                        Infolists\Components\TextEntry::make('id')->label('反馈 ID'),

                        Infolists\Components\TextEntry::make('type')
                            ->label('问题分类')
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

                        Infolists\Components\TextEntry::make('status')
                            ->label('处理状态')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state instanceof VideoDownloadFeedbackStatus ? $state->label() : VideoDownloadFeedbackStatus::tryFrom((int)$state)?->label() ?? '未知')
                            ->color(fn ($state) => match ($state instanceof VideoDownloadFeedbackStatus ? $state : VideoDownloadFeedbackStatus::tryFrom((int)$state)) {
                                VideoDownloadFeedbackStatus::PENDING => 'warning',
                                VideoDownloadFeedbackStatus::RESOLVED => 'success',
                                VideoDownloadFeedbackStatus::IGNORED => 'gray',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('提交时间')
                            ->dateTime('Y-m-d H:i:s'),

                        Infolists\Components\TextEntry::make('user.name')
                            ->label('反馈人')
                            ->default('匿名访客')
                            ->formatStateUsing(fn (?string $state, VideoDownloadFeedback $record) => $record->user ? ($record->user->nickname ?: $record->user->name) : '匿名访客'),

                        Infolists\Components\TextEntry::make('content')
                            ->label('用户详细说明')
                            ->placeholder('用户未提供补充文字')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('admin_notes')
                            ->label('管理员备注')
                            ->placeholder('暂无处理备注')
                            ->columnSpanFull(),
                    ])->columns(2),

                Infolists\Components\Section::make('关联资源与目标视频')
                    ->schema([
                        Infolists\Components\TextEntry::make('video.name')
                            ->label('关联视频')
                            ->formatStateUsing(fn (?string $state, VideoDownloadFeedback $record) => $record->video ? "[{$record->video->video_code}] {$record->video->name}" : '-')
                            ->url(fn (VideoDownloadFeedback $record) => $record->video_id ? VideoResource::getUrl('view', ['record' => $record->video_id]) : null, true),

                        Infolists\Components\TextEntry::make('download.title')
                            ->label('下载资源标题')
                            ->default('-'),

                        Infolists\Components\TextEntry::make('download.type')
                            ->label('资源类型')
                            ->badge()
                            ->color('info')
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'magnet' => 'BT 磁力',
                                'store' => '官方商城',
                                'link' => '直连/第三方',
                                'netdisk' => '网盘下载',
                                'torrent' => '种子文件',
                                default => $state ?: '-',
                            }),

                        Infolists\Components\TextEntry::make('download.resolution')
                            ->label('清晰度标称')
                            ->badge()
                            ->color('purple')
                            ->default('未标注'),

                        Infolists\Components\TextEntry::make('download.status')
                            ->label('资源当前上架状态')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state === 1 ? '正常上架' : '已下架/禁用')
                            ->color(fn ($state) => $state === 1 ? 'success' : 'danger'),

                        Infolists\Components\TextEntry::make('download.link')
                            ->label('下载/磁力链接')
                            ->copyable()
                            ->copyMessage('下载链接已复制')
                            ->columnSpanFull(),
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

                Tables\Columns\TextColumn::make('video.video_code')
                    ->label('关联视频')
                    ->description(fn (VideoDownloadFeedback $record) => $record->video ? mb_strimwidth($record->video->name, 0, 24, '...') : '')
                    ->url(fn (VideoDownloadFeedback $record) => $record->video_id ? VideoResource::getUrl('view', ['record' => $record->video_id]) : null, true)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('video', function (Builder $q) use ($search) {
                            $q->where('name', 'ILIKE', "%{$search}%")
                              ->orWhere('video_code', 'ILIKE', "%{$search}%");
                        });
                    }),

                Tables\Columns\TextColumn::make('download.title')
                    ->label('报错下载项')
                    ->description(function (VideoDownloadFeedback $record) {
                        if (!$record->download) return '资源已失效或删除';
                        $res = $record->download->resolution ? "[{$record->download->resolution}] " : '';
                        $typeStr = $record->download->type === 'magnet' ? '磁力' : ($record->download->type === 'store' ? '商城' : '链接');
                        $statusStr = $record->download->status === 1 ? '' : ' (已下架)';
                        return "{$res}{$typeStr}{$statusStr}";
                    })
                    ->wrap()
                    ->limit(35)
                    ->copyable()
                    ->copyableState(fn (VideoDownloadFeedback $record) => $record->download?->link)
                    ->tooltip(fn (VideoDownloadFeedback $record) => $record->download ? "点击复制链接: {$record->download->link}" : null),

                Tables\Columns\TextColumn::make('type')
                    ->label('报错类型')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof VideoDownloadFeedbackType ? $state->label() : VideoDownloadFeedbackType::tryFrom((int)$state)?->label() ?? '未知')
                    ->color(fn ($state) => match ($state instanceof VideoDownloadFeedbackType ? $state : VideoDownloadFeedbackType::tryFrom((int)$state)) {
                        VideoDownloadFeedbackType::DEAD_LINK,
                        VideoDownloadFeedbackType::PASSWORD_ERROR,
                        VideoDownloadFeedbackType::MALICIOUS_OR_AD => 'danger',
                        VideoDownloadFeedbackType::CONTENT_MISMATCH,
                        VideoDownloadFeedbackType::RESOLUTION_MISMATCH => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('content')
                    ->label('用户说明')
                    ->limit(25)
                    ->tooltip(fn (VideoDownloadFeedback $record) => $record->content ?: '无详细说明')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('反馈人')
                    ->default('匿名访客')
                    ->formatStateUsing(fn (?string $state, VideoDownloadFeedback $record) => $record->user ? ($record->user->nickname ?: $record->user->name) : '匿名访客')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('处理状态')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof VideoDownloadFeedbackStatus ? $state->label() : VideoDownloadFeedbackStatus::tryFrom((int)$state)?->label() ?? '未知')
                    ->color(fn ($state) => match ($state instanceof VideoDownloadFeedbackStatus ? $state : VideoDownloadFeedbackStatus::tryFrom((int)$state)) {
                        VideoDownloadFeedbackStatus::PENDING => 'warning',
                        VideoDownloadFeedbackStatus::RESOLVED => 'success',
                        VideoDownloadFeedbackStatus::IGNORED => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('admin_notes')
                    ->label('处理备注')
                    ->limit(20)
                    ->tooltip(fn (VideoDownloadFeedback $record) => $record->admin_notes)
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
                    ->options(array_combine(
                        array_map(fn($case) => $case->value, VideoDownloadFeedbackStatus::cases()),
                        array_map(fn($case) => $case->label(), VideoDownloadFeedbackStatus::cases())
                    )),

                Tables\Filters\SelectFilter::make('type')
                    ->label('报错类型')
                    ->options(array_combine(
                        array_map(fn($case) => $case->value, VideoDownloadFeedbackType::cases()),
                        array_map(fn($case) => $case->label(), VideoDownloadFeedbackType::cases())
                    )),
            ])
            ->actions([
                Tables\Actions\Action::make('handleFeedback')
                    ->label('处理')
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
                            ->placeholder('例如：已重新抓取并更新磁力链接 / 经迅雷与 qBittorrent 测试做种正常，判定为误报...')
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

                Tables\Actions\Action::make('toggleDownload')
                    ->label(fn (VideoDownloadFeedback $record) => $record->download?->status === 1 ? '下架资源' : '上架资源')
                    ->icon(fn (VideoDownloadFeedback $record) => $record->download?->status === 1 ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (VideoDownloadFeedback $record) => $record->download?->status === 1 ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (VideoDownloadFeedback $record) => $record->download?->status === 1 ? '确认下架该下载资源？' : '确认重新上架该下载资源？')
                    ->modalDescription('下架后前台将对普通用户隐藏此项资源。')
                    ->visible(fn (VideoDownloadFeedback $record) => (bool)$record->download)
                    ->action(function (VideoDownloadFeedback $record): void {
                        if ($record->download) {
                            $newStatus = $record->download->status === 1 ? 0 : 1;
                            $record->download->update(['status' => $newStatus]);

                            Notification::make()
                                ->title($newStatus === 1 ? '资源已重新上架' : '资源已成功下架')
                                ->success()
                                ->send();
                        }
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
                                    'status' => VideoDownloadFeedbackStatus::RESOLVED,
                                ]);
                            }
                            Notification::make()
                                ->title('已批量标记为已解决')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('batchDisableDownloads')
                        ->label('批量下架关联下载项')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            foreach ($records as $record) {
                                if ($record->download) {
                                    $record->download->update(['status' => 0]);
                                }
                                $record->update([
                                    'status' => VideoDownloadFeedbackStatus::RESOLVED,
                                    'admin_notes' => '批量下架失效资源',
                                ]);
                            }
                            Notification::make()
                                ->title('已批量下架关联资源并标记解决')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('batchIgnore')
                        ->label('批量标记为已忽略')
                        ->icon('heroicon-o-no-symbol')
                        ->color('gray')
                        ->action(function ($records): void {
                            foreach ($records as $record) {
                                $record->update([
                                    'status' => VideoDownloadFeedbackStatus::IGNORED,
                                ]);
                            }
                            Notification::make()
                                ->title('已批量标记为已忽略')
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
            'index' => Pages\ListVideoDownloadFeedbacks::route('/'),
            'view'  => Pages\ViewVideoDownloadFeedback::route('/{record}'),
        ];
    }
}

