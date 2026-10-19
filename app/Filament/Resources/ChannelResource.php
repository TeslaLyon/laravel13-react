<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChannelResource\Pages;
use App\Filament\Resources\ChannelResource\RelationManagers;
use App\Jobs\CrawlDorcelVideosJob;
use App\Jobs\CrawlJulesJordanVideosJob;
use App\Jobs\CrawlProject1VideosJob;
use App\Jobs\CrawlVixenDailyJob;
use App\Models\Channel;
use App\Services\Crawler\Project1VideoCrawlerService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class ChannelResource extends Resource
{
    protected static ?string $model = Channel::class;

    protected static ?string $navigationGroup = '片商与演员';

    protected static ?string $modelLabel = '片商';

    protected static ?string $pluralModelLabel = '片商管理';

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('片商核心资料')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('片商名称')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('slug')
                            ->label('Slug 标识')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('data_crawl_type')
                            ->label('采集与图片驱动类型')
                            ->options([
                                1 => '1 - 远程 CDN 直链模式 (Brazzers, BangBros, DorcelClub, JulesJordan 等)',
                                2 => '2 - 本地及 R2 云存储模式 (Vixen, Blacked, Tushy 等)',
                            ])
                            ->default(1)
                            ->required(),
                        Forms\Components\TextInput::make('official_website_url')
                            ->label('官方网址')
                            ->url()
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('visit')
                                    ->icon('heroicon-m-arrow-top-right-on-square')
                                    ->tooltip('在新标签页中访问官网')
                                    ->url(fn (?string $state): ?string => $state, shouldOpenInNewTab: true)
                            )
                            ->maxLength(255),
                        Forms\Components\TextInput::make('video_num')
                            ->label('已收录视频总数')
                            ->numeric()
                            ->default(0)
                            ->helperText('爬虫任务入库时会自动重新统计与校准'),
                        Forms\Components\TextInput::make('follow_num')
                            ->label('关注 / 订阅用户数')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Logo 与品牌视觉')
                    ->schema([
                        Forms\Components\Group::make([
                            Forms\Components\TextInput::make('avatar')
                                ->label('头像图片路径 (小图 / Avatar)')
                                ->placeholder('例如 /images/channels/brazzers-avatar.avif')
                                ->maxLength(255),
                            Forms\Components\Placeholder::make('avatar_preview')
                                ->label('头像预览')
                                ->content(function (?Channel $record): HtmlString {
                                    $url = static::resolveMediaUrl($record?->avatar);
                                    if (!$url) {
                                        return new HtmlString('<span class="text-xs text-gray-400">暂无头像数据</span>');
                                    }
                                    return new HtmlString("
                                        <div class=\"flex items-center gap-3 mt-1\">
                                            <img src=\"{$url}\" alt=\"头像\" class=\"w-14 h-14 rounded-full object-cover border border-gray-200 dark:border-gray-700 shadow-sm\" />
                                        </div>
                                    ");
                                }),
                        ]),
                        Forms\Components\Group::make([
                            Forms\Components\TextInput::make('logo')
                                ->label('Logo 图片路径 (大图 / Logo)')
                                ->placeholder('例如 /images/channels/brazzers-logo.avif')
                                ->maxLength(255),
                            Forms\Components\Placeholder::make('logo_preview')
                                ->label('Logo 预览')
                                ->content(function (?Channel $record): HtmlString {
                                    $url = static::resolveMediaUrl($record?->logo);
                                    if (!$url) {
                                        return new HtmlString('<span class="text-xs text-gray-400">暂无 Logo 数据</span>');
                                    }
                                    return new HtmlString("
                                        <div class=\"flex items-center gap-3 mt-1\">
                                            <img src=\"{$url}\" alt=\"Logo\" class=\"h-14 max-w-[200px] object-contain rounded border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-1 shadow-sm\" />
                                        </div>
                                    ");
                                }),
                        ]),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * 片商详情查看视图 (全字段展示)
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('片商核心信息')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label('片商名称')
                            ->weight('bold')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('slug')
                            ->label('Slug 标识')
                            ->badge()
                            ->color('gray')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('data_crawl_type')
                            ->label('采集与图片驱动模式')
                            ->badge()
                            ->formatStateUsing(fn ($state) => match ((int) $state) {
                                1 => '模式 1: 远端 CDN 直链免防盗链',
                                2 => '模式 2: 本地转码与 R2 云存储',
                                default => "未知模式 ({$state})",
                            })
                            ->color(fn ($state) => match ((int) $state) {
                                1 => 'success',
                                2 => 'info',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('official_website_url')
                            ->label('官方网址')
                            ->icon('heroicon-m-arrow-top-right-on-square')
                            ->url(fn (?string $state): ?string => $state, shouldOpenInNewTab: true)
                            ->color('primary')
                            ->placeholder('未配置官网'),
                        Infolists\Components\TextEntry::make('video_num')
                            ->label('已收录视频数')
                            ->numeric()
                            ->badge()
                            ->color('primary'),
                        Infolists\Components\TextEntry::make('follow_num')
                            ->label('关注/订阅用户数')
                            ->numeric()
                            ->badge()
                            ->color('warning'),
                        Infolists\Components\TextEntry::make('sites_count')
                            ->state(fn (Channel $record) => $record->sites()->count())
                            ->label('下辖站点 / 系列数')
                            ->numeric()
                            ->badge()
                            ->color('success'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('品牌视觉展示 (Avatar & Logo)')
                    ->schema([
                        Infolists\Components\TextEntry::make('visual_display')
                            ->label('视觉展示')
                            ->state(function (?Channel $record): HtmlString {
                                $avatarUrl = static::resolveMediaUrl($record?->avatar);
                                $logoUrl = static::resolveMediaUrl($record?->logo);

                                $avatarHtml = $avatarUrl
                                    ? "<div class=\"flex flex-col items-center gap-2\"><img src=\"{$avatarUrl}\" class=\"w-20 h-20 rounded-full object-cover border border-gray-200 dark:border-gray-700 shadow\" /><span class=\"text-xs text-gray-500\">头像 (Avatar)</span></div>"
                                    : '<span class="text-xs text-gray-400">暂无头像</span>';

                                $logoHtml = $logoUrl
                                    ? "<div class=\"flex flex-col items-center gap-2\"><img src=\"{$logoUrl}\" class=\"h-20 max-w-[260px] object-contain rounded border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-2 shadow\" /><span class=\"text-xs text-gray-500\">大图 Logo</span></div>"
                                    : '<span class="text-xs text-gray-400">暂无 Logo</span>';

                                return new HtmlString("
                                    <div class=\"flex items-center gap-8 py-2\">
                                        {$avatarHtml}
                                        {$logoHtml}
                                    </div>
                                    ");
                            })
                            ->html()
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('avatar')
                            ->label('头像原始路径 (avatar)')
                            ->color('gray')
                            ->fontFamily('mono'),
                        Infolists\Components\TextEntry::make('logo')
                            ->label('Logo 原始路径 (logo)')
                            ->color('gray')
                            ->fontFamily('mono'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('系统记录')
                    ->schema([
                        Infolists\Components\TextEntry::make('id')->label('片商自增 ID'),
                        Infolists\Components\TextEntry::make('created_at')->label('创建时间')->dateTime('Y-m-d H:i:s'),
                        Infolists\Components\TextEntry::make('updated_at')->label('最后更新时间')->dateTime('Y-m-d H:i:s'),
                        Infolists\Components\TextEntry::make('deleted_at')->label('软删除时间')->dateTime('Y-m-d H:i:s')->placeholder('正常（未删除）'),
                    ])
                    ->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')
                    ->label('头像')
                    ->circular()
                    ->height(40)
                    ->width(40)
                    ->checkFileExistence(false)
                    ->state(fn (Channel $record): ?string => static::resolveMediaUrl($record->avatar))
                    ->defaultImageUrl(fn (Channel $record) => "https://api.dicebear.com/7.x/identicon/svg?seed={$record->slug}"),
                Tables\Columns\ImageColumn::make('logo')
                    ->label('Logo')
                    ->height(36)
                    ->width(80)
                    ->checkFileExistence(false)
                    ->state(fn (Channel $record): ?string => static::resolveMediaUrl($record->logo))
                    ->extraImgAttributes(['class' => 'object-contain rounded border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-0.5 shadow-sm'])
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('name')
                    ->label('片商名称')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                Tables\Columns\TextColumn::make('data_crawl_type')
                    ->label('采集模式')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ((int) $state) {
                        1 => '直链模式 (1)',
                        2 => '本地/R2存储 (2)',
                        default => "未知({$state})",
                    })
                    ->color(fn ($state) => match ((int) $state) {
                        1 => 'success',
                        2 => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('sites_count')
                    ->counts('sites')
                    ->label('站点/系列数')
                    ->badge()
                    ->color('success')
                    ->sortable(),
                Tables\Columns\TextColumn::make('video_num')
                    ->label('视频数')
                    ->numeric()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('follow_num')
                    ->label('订阅数')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('official_website_url')
                    ->label('官方网址')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (?string $state): ?string => $state, shouldOpenInNewTab: true)
                    ->color('primary')
                    ->limit(25)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('最近更新')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'asc')
            ->recordUrl(fn (Channel $record): string => Pages\ViewChannel::getUrl([$record]))
            ->filters([
                Tables\Filters\SelectFilter::make('data_crawl_type')
                    ->label('采集类型')
                    ->options([
                        1 => 'Type 1 (远程 CDN 直链)',
                        2 => 'Type 2 (本地/R2存储)',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('crawl')
                    ->label('触发爬虫')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Channel $record) => "立即抓取片商 [{$record->name}]？")
                    ->modalDescription(function (Channel $record) {
                        return "系统将向后台 Horizon 队列投递片商 [{$record->name}] 的最新抓取任务（数据模式: Type {$record->data_crawl_type}）。";
                    })
                    ->action(function (Channel $record) {
                        $slug = $record->slug;
                        if (Project1VideoCrawlerService::isSupported($slug)) {
                            CrawlProject1VideosJob::dispatch(channel: $slug, limit: 24, page: 1);
                        } elseif ($slug === 'dorcelclub') {
                            CrawlDorcelVideosJob::dispatch(page: 1, skipExisting: true);
                        } elseif ($slug === 'julesjordan') {
                            CrawlJulesJordanVideosJob::dispatch(page: 1, skipExisting: true);
                        } else {
                            CrawlVixenDailyJob::dispatch($slug);
                        }

                        Notification::make()
                            ->title('抓取任务已派发')
                            ->body("已向后台队列投递片商 [{$record->name}] 的最新抓取 Job！")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SitesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListChannels::route('/'),
            'create' => Pages\CreateChannel::route('/create'),
            'view'   => Pages\ViewChannel::route('/{record}'),
            'edit'   => Pages\EditChannel::route('/{record}/edit'),
        ];
    }

    /**
     * 辅助方法：统一解析片商 Logo 或 Avatar 的完整访问 URL (兼容远程 URL 与 CDN)
     */
    public static function resolveMediaUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $cdnUrl = rtrim((string) config('app.cdn_url'), '/');
        $cleanPath = ltrim($path, '/');

        return $cdnUrl ? "{$cdnUrl}/{$cleanPath}" : asset($cleanPath);
    }
}
