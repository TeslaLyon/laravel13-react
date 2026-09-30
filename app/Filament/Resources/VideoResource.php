<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VideoResource\Pages;
use App\Models\Video;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

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
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('video_code')
                    ->label('编号')
                    ->searchable()
                    ->copyable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('name')
                    ->label('标题')
                    ->searchable()
                    ->limit(35)
                    ->tooltip(fn (Video $record): string => $record->name),
                Tables\Columns\TextColumn::make('channel.name')
                    ->label('片商')
                    ->badge()
                    ->color('info')
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
                Tables\Columns\TextColumn::make('release_at')
                    ->label('发布日期')
                    ->date('Y-m-d')
                    ->sortable(),
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
