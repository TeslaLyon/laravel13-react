<?php

namespace App\Filament\Resources\ChannelResource\RelationManagers;

use App\Models\Site;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SitesRelationManager extends RelationManager
{
    protected static string $relationship = 'sites';

    protected static ?string $title = '下辖站点 / 系列 (Sites)';

    protected static ?string $modelLabel = '站点系列';

    protected static ?string $pluralModelLabel = '站点系列列表';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('站点/系列名称')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('slug')
                    ->label('Slug 标识')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('short_name')
                    ->label('短代码标识')
                    ->placeholder('例如 bbe, bb')
                    ->maxLength(50),
                Forms\Components\TextInput::make('original_id')
                    ->label('远端 Collection ID')
                    ->numeric(),
                Forms\Components\TextInput::make('custom_url')
                    ->label('官方路由')
                    ->placeholder('例如 /websites/BangBros18')
                    ->maxLength(255),
                Forms\Components\TextInput::make('avatar')
                    ->label('Logo / 图标路径')
                    ->maxLength(255),
                Forms\Components\TextInput::make('video_num')
                    ->label('下辖视频总数')
                    ->numeric()
                    ->default(0),
                Forms\Components\Toggle::make('status')
                    ->label('正常状态')
                    ->default(true),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('系列/站点名称')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                Tables\Columns\TextColumn::make('short_name')
                    ->label('短代码')
                    ->badge()
                    ->color('info')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('original_id')
                    ->label('远端 ID')
                    ->color('gray')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('custom_url')
                    ->label('官方路由')
                    ->color('gray')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('video_num')
                    ->label('视频数')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\ToggleColumn::make('status')
                    ->label('状态'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}

