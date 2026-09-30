<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChannelResource\Pages;
use App\Jobs\CrawlVixenDailyJob;
use App\Models\Channel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

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
                Forms\Components\Section::make('片商基本信息')
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
                                1 => '1 - 源站直链免鉴权 (Project1 / Brazzers等)',
                                2 => '2 - 本地及云盘存储 (Vixen / Blacked等)',
                            ])
                            ->default(2)
                            ->required(),
                        Forms\Components\TextInput::make('official_website_url')
                            ->label('官方网址')
                            ->url()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('avatar')
                            ->label('头像路径 (小图)')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('logo')
                            ->label('Logo 路径 (大图)')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('video_num')
                            ->label('已收录视频总数')
                            ->numeric()
                            ->default(0)
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')
                    ->label('头像')
                    ->circular(),
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
                    ->label('数据模式')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ((int) $state) {
                        1 => '直链源站 (1)',
                        2 => '云盘/本地存储 (2)',
                        default => "未知({$state})",
                    })
                    ->color(fn ($state) => match ((int) $state) {
                        1 => 'success',
                        2 => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('video_num')
                    ->label('视频数')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('最近更新')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('data_crawl_type')
                    ->label('采集类型')
                    ->options([
                        1 => 'Type 1 (直链免防盗链)',
                        2 => 'Type 2 (本地/R2存储)',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('crawl')
                    ->label('触发爬虫')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Channel $record) => "立即抓取 {$record->name}？")
                    ->modalDescription('系统将向后台 Horizon 队列投递该片商的最新增量抓取任务。')
                    ->action(function (Channel $record) {
                        CrawlVixenDailyJob::dispatch($record->slug);
                        Notification::make()
                            ->title('抓取任务已派发')
                            ->body("已向后台队列投递片商 [{$record->name}] 的最新抓取 Job！")
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Channel $record) => (int) $record->data_crawl_type === 2),
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
            'index' => Pages\ListChannels::route('/'),
            'create' => Pages\CreateChannel::route('/create'),
            'edit' => Pages\EditChannel::route('/{record}/edit'),
        ];
    }
}
