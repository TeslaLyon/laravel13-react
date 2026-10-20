<?php

namespace App\Filament\Resources\VideoResource\RelationManagers;

use App\Models\VideoHistory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class HistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'histories';

    protected static ?string $title = '资料修改与审计记录';

    protected static ?string $modelLabel = '修改记录';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('action_type')->label('变更动作')->disabled(),
                Forms\Components\TextInput::make('remark')->label('说明')->columnSpanFull()->disabled(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('action_type')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),

                Tables\Columns\TextColumn::make('action_type')
                    ->label('变更类型')
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'update_name_zh', 'update_title_cn' => '中文标题更新',
                        'update_description'                => '视频简介更新',
                        'update_actors'                     => '参演演员调整',
                        'update_categories'                 => '视频分类调整',
                        'update_tags'                       => '标签调整',
                        default                             => $state,
                    }),

                Tables\Columns\TextColumn::make('operator.name')
                    ->label('操作人')
                    ->default('系统自动'),

                Tables\Columns\TextColumn::make('old_values')
                    ->label('修改前数据快照')
                    ->state(fn (VideoHistory $record) => is_array($record->old_values) ? json_encode($record->old_values, JSON_UNESCAPED_UNICODE) : (string)$record->old_values)
                    ->limit(35)
                    ->tooltip(fn (VideoHistory $record) => is_array($record->old_values) ? json_encode($record->old_values, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : (string)$record->old_values),

                Tables\Columns\TextColumn::make('new_values')
                    ->label('修改后数据快照')
                    ->state(fn (VideoHistory $record) => is_array($record->new_values) ? json_encode($record->new_values, JSON_UNESCAPED_UNICODE) : (string)$record->new_values)
                    ->limit(35)
                    ->tooltip(fn (VideoHistory $record) => is_array($record->new_values) ? json_encode($record->new_values, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : (string)$record->new_values),

                Tables\Columns\TextColumn::make('remark')
                    ->label('变更批注')
                    ->limit(30)
                    ->tooltip(fn (VideoHistory $record) => $record->remark),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('修改时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([])
            ->headerActions([])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }
}

