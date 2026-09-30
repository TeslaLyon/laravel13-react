<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VideoResource\Pages;
use App\Models\Video;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
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
                Forms\Components\Section::make('基本信息')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('原始标题')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('name_zh')
                            ->label('中文标题')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('slug')
                            ->label('Slug 标识')
                            ->required()
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
                        Forms\Components\DateTimePicker::make('release_at')
                            ->label('发布时间'),
                        Forms\Components\TextInput::make('max_quality')
                            ->label('最高画质')
                            ->placeholder('例如 2160p, 1080p')
                            ->maxLength(20),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('特性与状态')
                    ->schema([
                        Forms\Components\Toggle::make('status')
                            ->label('是否上架')
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
                    ])
                    ->columns(4),

                Forms\Components\Section::make('封面与媒体数据')
                    ->schema([
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
                            }),
                        Forms\Components\Textarea::make('list_img')
                            ->label('封面原始 JSON 数据')
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) $state)
                            ->rows(5)
                            ->columnSpanFull()
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->collapsible()
                    ->collapsed(true),
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
                Tables\Columns\TextColumn::make('release_at')
                    ->label('发布时间')
                    ->dateTime('Y-m-d')
                    ->sortable(),
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
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('channel_id')
                    ->label('片商')
                    ->relationship('channel', 'name')
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
            'index' => Pages\ListVideos::route('/'),
            'create' => Pages\CreateVideo::route('/create'),
            'edit' => Pages\EditVideo::route('/{record}/edit'),
        ];
    }
}
