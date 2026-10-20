<?php

namespace App\Filament\Resources\VideoResource\RelationManagers;

use App\Enums\FeedbackStatus;
use App\Models\Feedback;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class FeedbackRelationManager extends RelationManager
{
    protected static string $relationship = 'feedback';

    protected static ?string $title = '用户反馈记录';

    protected static ?string $modelLabel = '用户反馈';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('type')->label('分类')->disabled(),
                Forms\Components\Textarea::make('content')->label('详情')->disabled()->columnSpanFull(),
                Forms\Components\Select::make('status')
                    ->label('状态')
                    ->options([
                        FeedbackStatus::PENDING->value    => '待处理',
                        FeedbackStatus::PROCESSING->value => '处理中',
                        FeedbackStatus::RESOLVED->value   => '已解决',
                    ]),
                Forms\Components\Textarea::make('admin_reply')->label('答复')->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('反馈分类')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('content')
                    ->label('反馈说明')
                    ->limit(40)
                    ->tooltip(fn (Feedback $record) => $record->content),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('反馈人')
                    ->default('匿名访客'),

                Tables\Columns\TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof FeedbackStatus ? $state->label() : FeedbackStatus::tryFrom((int)$state)?->label() ?? '未知')
                    ->color(fn ($state) => match ($state instanceof FeedbackStatus ? $state : FeedbackStatus::tryFrom((int)$state)) {
                        FeedbackStatus::PENDING    => 'warning',
                        FeedbackStatus::PROCESSING => 'info',
                        FeedbackStatus::RESOLVED   => 'success',
                        default                    => 'gray',
                    }),

                Tables\Columns\TextColumn::make('admin_reply')
                    ->label('处理答复')
                    ->limit(30)
                    ->default('-'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('反馈时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('handle')
                    ->label('处理')
                    ->icon('heroicon-o-check')
                    ->color('primary')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label('状态')
                            ->options([
                                FeedbackStatus::PROCESSING->value => '处理中',
                                FeedbackStatus::RESOLVED->value   => '已解决',
                            ])
                            ->default(FeedbackStatus::RESOLVED->value)
                            ->required(),
                        Forms\Components\Textarea::make('admin_reply')
                            ->label('答复/说明')
                            ->rows(2),
                    ])
                    ->action(function (Feedback $record, array $data): void {
                        $record->update([
                            'status'      => $data['status'],
                            'admin_reply' => $data['admin_reply'] ?? null,
                            'handled_by'  => auth()->id(),
                            'handled_at'  => now(),
                        ]);
                        Notification::make()->title('已更新反馈状态')->success()->send();
                    }),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }
}

