<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActorResource\Pages;
use App\Models\Actor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ActorResource extends Resource
{
    protected static ?string $model = Actor::class;

    protected static ?string $navigationGroup = '片商与演员';

    protected static ?string $modelLabel = '演员';

    protected static ?string $pluralModelLabel = '演员管理';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('演员资料')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('姓名 / 艺名')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('slug')
                            ->label('Slug 标识')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('gender')
                            ->label('性别')
                            ->options([
                                'female' => '女性',
                                'male'   => '男性',
                            ])
                            ->default('female'),
                        Forms\Components\Toggle::make('is_trans_model')
                            ->label('是否为变性模特')
                            ->default(false),
                        Forms\Components\TextInput::make('avatar')
                            ->label('头像图片路径')
                            ->maxLength(255)
                            ->columnSpanFull(),
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
                    ->circular()
                    ->checkFileExistence(false)
                    ->state(function (\App\Models\Actor $record): ?string {
                        if (empty($record->avatar)) {
                            return null;
                        }

                        if (str_starts_with($record->avatar, 'http://') || str_starts_with($record->avatar, 'https://')) {
                            return $record->avatar;
                        }

                        $cdnUrl = rtrim((string) config('app.cdn_url'), '/');
                        $cleanPath = ltrim($record->avatar, '/');

                        return $cdnUrl ? "{$cdnUrl}/{$cleanPath}" : asset($cleanPath);
                    })
                    ->defaultImageUrl(fn (\App\Models\Actor $record) => "https://api.dicebear.com/7.x/identicon/svg?seed={$record->slug}"),
                Tables\Columns\TextColumn::make('name')
                    ->label('姓名')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                Tables\Columns\TextColumn::make('gender')
                    ->label('性别')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'female' => '女',
                        'male'   => '男',
                        default  => $state ?: '未知',
                    })
                    ->color(fn ($state) => match ($state) {
                        'female' => 'danger',
                        'male'   => 'info',
                        default  => 'gray',
                    }),
                Tables\Columns\TextColumn::make('videos_count')
                    ->label('参演视频')
                    ->counts('videos')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('gender')
                    ->label('性别')
                    ->options([
                        'female' => '女性',
                        'male'   => '男性',
                    ]),
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
            'index' => Pages\ListActors::route('/'),
            'create' => Pages\CreateActor::route('/create'),
            'edit' => Pages\EditActor::route('/{record}/edit'),
        ];
    }
}
