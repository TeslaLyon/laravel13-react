<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VideoResource\Pages;
use App\Models\Video;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class VideoResource extends Resource
{
    protected static ?string $model = Video::class;

    protected static ?string $navigationGroup = '视频内容管理';

    protected static ?string $modelLabel = '视频';

    protected static ?string $pluralModelLabel = '视频管理';

    protected static ?string $navigationIcon = 'heroicon-o-film';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('视频信息编辑')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('基础信息')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('原始标题')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('name_zh')
                                    ->label('中文标题')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('video_code')
                                    ->label('视频编号')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Select::make('channel_id')
                                    ->label('所属片商')
                                    ->relationship('channel', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Forms\Components\Select::make('sites')
                                    ->label('所属站点 / 系列')
                                    ->relationship('sites', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->searchable(),
                                Forms\Components\TextInput::make('slug')
                                    ->label('Slug 路由标识')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('source_uuid')
                                    ->label('数据源 UUID (source_uuid)')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(2),
                                Forms\Components\DateTimePicker::make('release_at')
                                    ->label('发布时间'),
                                Forms\Components\TextInput::make('max_quality')
                                    ->label('最高画质')
                                    ->placeholder('例如 2160p, 1080p')
                                    ->maxLength(20),
                                Forms\Components\Select::make('sexual_orientation')
                                    ->label('性取向')
                                    ->options([
                                        0 => '未知',
                                        1 => '异性恋 (Straight)',
                                        2 => '女同性恋 (Lesbian)',
                                    ])
                                    ->default(1),
                                Forms\Components\TextInput::make('country')
                                    ->label('国家/地区')
                                    ->maxLength(10),
                            ])
                            ->columns(3),

                        Forms\Components\Tabs\Tab::make('详情与简介')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Forms\Components\Textarea::make('videoDetail.description')
                                    ->label('视频简介 / 剧情介绍')
                                    ->rows(5)
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('videoDetail.movie_length')
                                    ->label('视频时长（秒）')
                                    ->numeric()
                                    ->helperText('单位：秒，系统前台将自动换算显示为 分:秒'),
                                Forms\Components\TextInput::make('videoDetail.video_urls')
                                    ->label('预告片 / 视频直链')
                                    ->url()
                                    ->columnSpan(2),
                            ])
                            ->columns(3),

                        Forms\Components\Tabs\Tab::make('演员与分类')
                            ->icon('heroicon-o-user-group')
                            ->schema([
                                Forms\Components\Select::make('actors')
                                    ->label('关联演员')
                                    ->relationship('actors', 'name')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->columnSpanFull(),
                                Forms\Components\Select::make('categories')
                                    ->label('关联标签 / 分类')
                                    ->relationship('categories', 'name')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Tabs\Tab::make('特性与状态')
                            ->icon('heroicon-o-adjustments-horizontal')
                            ->schema([
                                Forms\Components\Toggle::make('status')
                                    ->label('上架状态')
                                    ->default(true),
                                Forms\Components\Toggle::make('is_4k')
                                    ->label('4K 画质')
                                    ->default(false),
                                Forms\Components\Toggle::make('is_vr')
                                    ->label('VR 视频')
                                    ->default(false),
                                Forms\Components\Toggle::make('is_trans_model')
                                    ->label('变性演员')
                                    ->default(false),
                                Forms\Components\Toggle::make('has_zh_subtitles')
                                    ->label('包含中文字幕')
                                    ->default(false),
                                Forms\Components\Toggle::make('has_downloads')
                                    ->label('包含下载项')
                                    ->default(false),
                            ])
                            ->columns(3),

                        Forms\Components\Tabs\Tab::make('封面与媒体数据')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                Forms\Components\TextInput::make('preview')
                                    ->label('鼠标悬停预览配置 (Preview)')
                                    ->placeholder('1<视频直链 或 2<动图 或 3<多张切片图片URL')
                                    ->columnSpanFull(),
                                Forms\Components\Placeholder::make('list_img_preview')
                                    ->label('封面预览')
                                    ->content(function (?Video $record): HtmlString {
                                        if (!$record || empty($record->list_img)) {
                                            return new HtmlString('<span class="text-sm text-gray-400">暂无封面数据</span>');
                                        }

                                        $listImg = is_string($record->list_img) ? json_decode($record->list_img, true) : $record->list_img;
                                        $first = is_array($listImg) ? ($listImg[0] ?? null) : null;
                                        if (!is_array($first)) {
                                            return new HtmlString('<span class="text-sm text-gray-400">暂无封面数据</span>');
                                        }

                                        $rawUrl = $first['highdpi']['double']
                                            ?? $first['src']
                                            ?? $first['src_source']
                                            ?? $first['placeholder']
                                            ?? null;

                                        if (empty($rawUrl)) {
                                            return new HtmlString('<span class="text-sm text-gray-400">暂无封面数据</span>');
                                        }

                                        $cdnUrl = rtrim((string) config('app.cdn_url'), '/');
                                        $cleanPath = ltrim($rawUrl, '/');
                                        $fullUrl = (str_starts_with($rawUrl, 'http://') || str_starts_with($rawUrl, 'https://'))
                                            ? $rawUrl
                                            : ($cdnUrl ? "{$cdnUrl}/{$cleanPath}" : asset($cleanPath));

                                        $width = $first['width'] ?? 0;
                                        $height = $first['height'] ?? 0;
                                        $metaText = ($width && $height) ? "<div class=\"text-xs text-gray-500 dark:text-gray-400 mt-1.5 font-mono\">分辨率: {$width} × {$height}</div>" : '';

                                        return new HtmlString("
                                            <div>
                                                <img src=\"{$fullUrl}\" alt=\"视频封面\" class=\"h-44 rounded-lg object-cover shadow-sm border border-gray-200 dark:border-gray-700\" />
                                                {$metaText}
                                            </div>
                                        ");
                                    })
                                    ->columnSpanFull(),
                                Forms\Components\Textarea::make('list_img')
                                    ->label('封面原始 JSON 数据')
                                    ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) $state)
                                    ->rows(6)
                                    ->columnSpanFull()
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * 视频详情查看视图 (全字段、全关联信息聚合展示)
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Tabs::make('视频全维度数据详情')
                    ->tabs([
                        Infolists\Components\Tabs\Tab::make('基础信息')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Infolists\Components\Section::make('核心元数据')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('name')
                                            ->label('原始标题')
                                            ->weight('bold')
                                            ->copyable()
                                            ->columnSpan(2),
                                        Infolists\Components\TextEntry::make('name_zh')
                                            ->label('中文标题')
                                            ->placeholder('暂无中文标题')
                                            ->copyable(),
                                        Infolists\Components\TextEntry::make('video_code')
                                            ->label('视频编号 (Video Code)')
                                            ->badge()
                                            ->color('primary')
                                            ->copyable(),
                                        Infolists\Components\TextEntry::make('channel.name')
                                            ->label('所属片商 (Channel)')
                                            ->badge()
                                            ->color('info'),
                                        Infolists\Components\TextEntry::make('sites.name')
                                            ->label('所属站点 / 系列 (Sites)')
                                            ->badge()
                                            ->color('success')
                                            ->placeholder('无特定系列'),
                                        Infolists\Components\TextEntry::make('slug')
                                            ->label('URL Slug')
                                            ->copyable()
                                            ->color('gray'),
                                        Infolists\Components\TextEntry::make('source_uuid')
                                            ->label('源 UUID (source_uuid)')
                                            ->copyable()
                                            ->fontFamily('mono')
                                            ->color('gray')
                                            ->columnSpan(2),
                                        Infolists\Components\TextEntry::make('release_at')
                                            ->label('官方发布时间')
                                            ->dateTime('Y-m-d H:i:s')
                                            ->placeholder('未标注'),
                                        Infolists\Components\TextEntry::make('videoDetail.movie_length')
                                            ->label('视频时长')
                                            ->formatStateUsing(function ($state) {
                                                if (!$state || $state <= 0) return '未知';
                                                $m = floor($state / 60);
                                                $s = $state % 60;
                                                return "{$m} 分 {$s} 秒 ({$state} 秒)";
                                            })
                                            ->badge()
                                            ->color('gray'),
                                        Infolists\Components\TextEntry::make('max_quality')
                                            ->label('最高画质')
                                            ->badge()
                                            ->color('warning')
                                            ->placeholder('未标注'),
                                        Infolists\Components\TextEntry::make('sexual_orientation')
                                            ->label('性取向')
                                            ->formatStateUsing(fn ($state) => match ((int) $state) {
                                                1 => '异性恋 (Straight)',
                                                2 => '女同性恋 (Lesbian)',
                                                default => '未知',
                                            })
                                            ->badge(),
                                        Infolists\Components\TextEntry::make('country')
                                            ->label('国家 / 地区')
                                            ->badge()
                                            ->placeholder('未知'),
                                    ])
                                    ->columns(3),

                                Infolists\Components\Section::make('属性与上架状态')
                                    ->schema([
                                        Infolists\Components\IconEntry::make('status')
                                            ->label('上架状态')
                                            ->boolean(),
                                        Infolists\Components\IconEntry::make('is_4k')
                                            ->label('4K 超高清')
                                            ->boolean(),
                                        Infolists\Components\IconEntry::make('is_vr')
                                            ->label('VR 视频')
                                            ->boolean(),
                                        Infolists\Components\IconEntry::make('is_trans_model')
                                            ->label('变性模特')
                                            ->boolean(),
                                        Infolists\Components\IconEntry::make('has_zh_subtitles')
                                            ->label('包含中文字幕')
                                            ->boolean(),
                                        Infolists\Components\IconEntry::make('has_downloads')
                                            ->label('包含下载项')
                                            ->boolean(),
                                    ])
                                    ->columns(6),

                                Infolists\Components\Section::make('前台交互统计')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('views_count')
                                            ->label('浏览 / 播放量')
                                            ->numeric()
                                            ->default(0),
                                        Infolists\Components\TextEntry::make('likes_count')
                                            ->label('点赞数')
                                            ->numeric()
                                            ->default(0),
                                        Infolists\Components\TextEntry::make('favorites_count')
                                            ->label('收藏数')
                                            ->numeric()
                                            ->default(0),
                                    ])
                                    ->columns(3),
                            ]),

                        Infolists\Components\Tabs\Tab::make('详情与简介')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Infolists\Components\TextEntry::make('videoDetail.description')
                                    ->label('剧情介绍 / 视频简介')
                                    ->placeholder('暂无简介')
                                    ->columnSpanFull(),
                                Infolists\Components\TextEntry::make('videoDetail.video_urls')
                                    ->label('预告片 / 视频播放直链')
                                    ->copyable()
                                    ->placeholder('暂无播放直链')
                                    ->columnSpanFull(),
                            ]),

                        Infolists\Components\Tabs\Tab::make('演员与分类')
                            ->icon('heroicon-o-user-group')
                            ->schema([
                                Infolists\Components\RepeatableEntry::make('actors')
                                    ->label('参演演员')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('name')
                                            ->label('姓名 / 艺名')
                                            ->weight('bold'),
                                        Infolists\Components\TextEntry::make('gender')
                                            ->label('性别')
                                            ->formatStateUsing(fn ($state) => match ((int) $state) {
                                                1 => '男 (Male)',
                                                2 => '女 (Female)',
                                                3 => '变性人 (Trans)',
                                                default => '未知',
                                            })
                                            ->badge(),
                                        Infolists\Components\TextEntry::make('slug')
                                            ->label('Slug')
                                            ->color('gray'),
                                    ])
                                    ->grid(3)
                                    ->columnSpanFull(),

                                Infolists\Components\TextEntry::make('categories.name')
                                    ->label('视频标签 / 分类')
                                    ->badge()
                                    ->color('gray')
                                    ->placeholder('暂无标签')
                                    ->columnSpanFull(),
                            ]),

                        Infolists\Components\Tabs\Tab::make('多媒体与剧照')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                Infolists\Components\TextEntry::make('preview')
                                    ->label('鼠标悬停预览 (Preview 原始配置)')
                                    ->copyable()
                                    ->placeholder('暂无预览配置')
                                    ->columnSpanFull(),

                                Infolists\Components\Placeholder::make('screen_img_gallery')
                                    ->label('剧照画廊 (Screen Images)')
                                    ->content(function (?Video $record): HtmlString {
                                        $screenImgs = $record?->videoDetail?->screen_img;
                                        if (empty($screenImgs) || !is_array($screenImgs)) {
                                            return new HtmlString('<span class="text-sm text-gray-400">暂无剧照数据</span>');
                                        }

                                        $cdnUrl = rtrim((string) config('app.cdn_url'), '/');
                                        $html = '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3 mt-2">';
                                        foreach ($screenImgs as $idx => $img) {
                                            $thumb = $img['source_url'] ?? $img['url'] ?? $img['src'] ?? null;
                                            $full = $img['full_source_url'] ?? $img['full_url'] ?? $thumb;
                                            if (!$thumb) continue;

                                            $cleanThumb = (str_starts_with($thumb, 'http://') || str_starts_with($thumb, 'https://'))
                                                ? $thumb
                                                : ($cdnUrl ? "{$cdnUrl}/" . ltrim($thumb, '/') : asset(ltrim($thumb, '/')));

                                            $cleanFull = (str_starts_with($full, 'http://') || str_starts_with($full, 'https://'))
                                                ? $full
                                                : ($cdnUrl ? "{$cdnUrl}/" . ltrim($full, '/') : asset(ltrim($full, '/')));

                                            $w = $img['full_width'] ?? $img['width'] ?? 0;
                                            $h = $img['full_height'] ?? $img['height'] ?? 0;
                                            $dim = ($w && $h) ? "<div class=\"text-[11px] text-gray-500 text-center mt-1 font-mono\">{$w}×{$h}</div>" : '';

                                            $html .= "<div class=\"group relative rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-1\">
                                                <a href=\"{$cleanFull}\" target=\"_blank\" class=\"block overflow-hidden rounded\">
                                                    <img src=\"{$cleanThumb}\" class=\"w-full h-24 object-cover rounded hover:scale-105 transition-transform duration-200\" loading=\"lazy\" />
                                                </a>
                                                {$dim}
                                            </div>";
                                        }
                                        $html .= '</div>';
                                        return new HtmlString($html);
                                    })
                                    ->columnSpanFull(),

                                Infolists\Components\Section::make('原始 JSON 媒体元数据')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('list_img')
                                            ->label('封面原始 JSON (list_img)')
                                            ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
                                            ->fontFamily('mono')
                                            ->columnSpanFull(),
                                        Infolists\Components\TextEntry::make('videoDetail.list_img_large_meta')
                                            ->label('大图响应式元数据 (list_img_large_meta)')
                                            ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
                                            ->fontFamily('mono')
                                            ->columnSpanFull(),
                                        Infolists\Components\TextEntry::make('videoDetail.screen_img')
                                            ->label('剧照原始元数据 (screen_img)')
                                            ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
                                            ->fontFamily('mono')
                                            ->columnSpanFull(),
                                    ])
                                    ->collapsible()
                                    ->collapsed(true)
                                    ->columnSpanFull(),
                            ]),

                        Infolists\Components\Tabs\Tab::make('系统与时间')
                            ->icon('heroicon-o-clock')
                            ->schema([
                                Infolists\Components\Section::make('数据库记录与时间戳')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('id')->label('视频自增 ID (id)'),
                                        Infolists\Components\TextEntry::make('videoDetail.id')->label('详情记录 ID (video_detail_id)'),
                                        Infolists\Components\TextEntry::make('created_at')->label('首次抓取收录时间')->dateTime('Y-m-d H:i:s'),
                                        Infolists\Components\TextEntry::make('updated_at')->label('最后更新时间')->dateTime('Y-m-d H:i:s'),
                                        Infolists\Components\TextEntry::make('deleted_at')->label('软删除时间')->dateTime('Y-m-d H:i:s')->placeholder('正常（未删除）'),
                                    ])
                                    ->columns(3),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('list_img')
                    ->label('封面')
                    ->height(45)
                    ->width(80)
                    ->checkFileExistence(false)
                    ->state(function (Video $record): ?string {
                        $listImg = $record->list_img;
                        if (is_string($listImg)) {
                            $listImg = json_decode($listImg, true);
                        }
                        if (!is_array($listImg) || empty($listImg)) {
                            return null;
                        }

                        $first = $listImg[0] ?? null;
                        if (!is_array($first)) {
                            return null;
                        }

                        $rawUrl = $first['highdpi']['double']
                            ?? $first['src']
                            ?? $first['webp']['highdpi']['double']
                            ?? $first['webp']['src']
                            ?? $first['src_source']
                            ?? $first['placeholder']
                            ?? null;

                        if (empty($rawUrl)) {
                            return null;
                        }

                        if (str_starts_with($rawUrl, 'http://') || str_starts_with($rawUrl, 'https://')) {
                            return $rawUrl;
                        }

                        $cdnUrl = rtrim((string) config('app.cdn_url'), '/');
                        $cleanPath = ltrim($rawUrl, '/');

                        return $cdnUrl ? "{$cdnUrl}/{$cleanPath}" : asset($cleanPath);
                    })
                    ->extraImgAttributes(['class' => 'object-cover rounded shadow-sm'])
                    ->defaultImageUrl('https://api.dicebear.com/7.x/shapes/svg?seed=video'),
                Tables\Columns\TextColumn::make('video_code')
                    ->label('编号')
                    ->searchable()
                    ->copyable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('name')
                    ->label('原始标题')
                    ->searchable()
                    ->limit(25)
                    ->tooltip(fn (Video $record): string => $record->name),
                Tables\Columns\TextColumn::make('name_zh')
                    ->label('中文标题')
                    ->searchable()
                    ->limit(25)
                    ->placeholder('-')
                    ->tooltip(fn (Video $record): ?string => $record->name_zh),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->limit(20)
                    ->color('gray')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('channel.name')
                    ->label('片商')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sites.name')
                    ->label('站点/系列')
                    ->badge()
                    ->color('success')
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('release_at')
                    ->label('发布时间')
                    ->dateTime('Y-m-d')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw("release_at {$direction} NULLS LAST")->orderBy('id', 'desc'))
                    ->placeholder('-'),
                Tables\Columns\ToggleColumn::make('status')
                    ->label('上架状态'),
                Tables\Columns\IconColumn::make('is_4k')
                    ->label('4K')
                    ->boolean(),
                Tables\Columns\TextColumn::make('max_quality')
                    ->label('画质')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('收录时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('release_at', 'desc')
            ->recordUrl(fn (Video $record): string => Pages\ViewVideo::getUrl([$record]))
            ->filters([
                Tables\Filters\SelectFilter::make('channel_id')
                    ->label('片商')
                    ->relationship('channel', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('sites')
                    ->label('站点/系列')
                    ->relationship('sites', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->label('状态')
                    ->options([
                        1 => '已上架',
                        0 => '已下架',
                    ]),
                Tables\Filters\TernaryFilter::make('is_4k')
                    ->label('4K 视频'),
            ])
            ->actions([
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListVideos::route('/'),
            'create' => Pages\CreateVideo::route('/create'),
            'view'   => Pages\ViewVideo::route('/{record}'),
            'edit'   => Pages\EditVideo::route('/{record}/edit'),
        ];
    }
}
