<?php

namespace App\Filament\Resources;

use App\Enums\VideoCorrectionStatus;
use App\Filament\Resources\VideoCorrectionResource\Pages;
use App\Models\Actor;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Video;
use App\Models\VideoCorrection;
use App\Services\VideoCorrectionAuditService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Throwable;

class VideoCorrectionResource extends Resource
{
    protected static ?string $model = VideoCorrection::class;

    protected static ?string $navigationGroup = '反馈与审核';

    protected static ?string $modelLabel = '修正资料';

    protected static ?string $pluralModelLabel = '修正资料';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $count = VideoCorrection::where('status', VideoCorrectionStatus::PENDING)->count();
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
                Forms\Components\Section::make('修正申请信息')
                    ->schema([
                        Forms\Components\Placeholder::make('video_title')
                            ->label('目标视频')
                            ->content(fn (?VideoCorrection $record) => $record?->video ? "[#{$record->video->id}] {$record->video->name} ({$record->video->video_code})" : '-'),

                        Forms\Components\Placeholder::make('user_name')
                            ->label('提交用户')
                            ->content(fn (?VideoCorrection $record) => $record?->user ? ($record->user->nickname ?: $record->user->name) : "用户#{$record?->user_id}"),

                        Forms\Components\Placeholder::make('type_name')
                            ->label('修正维度')
                            ->content(fn (?VideoCorrection $record) => $record ? app(VideoCorrectionAuditService::class)->getTypeLabel($record->type) : '-'),

                        Forms\Components\Placeholder::make('status_badge')
                            ->label('当前状态')
                            ->content(fn (?VideoCorrection $record) => $record?->status instanceof VideoCorrectionStatus ? $record->status->label() : VideoCorrectionStatus::tryFrom((int)$record?->status)?->label() ?? '-'),

                        Forms\Components\Placeholder::make('comparison')
                            ->label('数据变动对比 (修改前 vs 用户建议)')
                            ->content(fn (?VideoCorrection $record) => $record ? self::renderDiffHtml($record) : '')
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('reject_reason')
                            ->label('驳回原因')
                            ->content(fn (?VideoCorrection $record) => $record?->reject_reason ?: '无')
                            ->visible(fn (?VideoCorrection $record) => $record?->status === VideoCorrectionStatus::REJECTED)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('修正申请详情与审核记录')
                    ->schema([
                        Infolists\Components\TextEntry::make('id')->label('提案 ID'),

                        Infolists\Components\TextEntry::make('type')
                            ->label('修正维度')
                            ->badge()
                            ->color('info')
                            ->formatStateUsing(fn ($state) => app(VideoCorrectionAuditService::class)->getTypeLabel($state)),

                        Infolists\Components\TextEntry::make('status')
                            ->label('审核状态')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state instanceof VideoCorrectionStatus ? $state->label() : VideoCorrectionStatus::tryFrom((int)$state)?->label() ?? '未知')
                            ->color(fn ($state) => match ($state instanceof VideoCorrectionStatus ? $state : VideoCorrectionStatus::tryFrom((int)$state)) {
                                VideoCorrectionStatus::PENDING  => 'warning',
                                VideoCorrectionStatus::APPROVED => 'success',
                                VideoCorrectionStatus::REJECTED => 'danger',
                                default                         => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('提交时间')
                            ->dateTime('Y-m-d H:i:s'),

                        Infolists\Components\TextEntry::make('video.name')
                            ->label('关联视频')
                            ->url(fn (VideoCorrection $record) => $record->video_id ? VideoResource::getUrl('view', ['record' => $record->video_id]) : null, true)
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('user.name')
                            ->label('建议提交人')
                            ->default(fn (VideoCorrection $record) => "用户 #{$record->user_id}"),

                        Infolists\Components\TextEntry::make('reviewer.name')
                            ->label('审核管理员')
                            ->default('待审核'),

                        Infolists\Components\TextEntry::make('reviewed_at')
                            ->label('审核时间')
                            ->dateTime('Y-m-d H:i:s')
                            ->default('-'),

                        Infolists\Components\TextEntry::make('diff')
                            ->label('数据比对与修改建议')
                            ->state(fn (VideoCorrection $record) => self::renderDiffHtml($record))
                            ->html()
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('reject_reason')
                            ->label('驳回理由')
                            ->visible(fn (VideoCorrection $record) => $record->status === VideoCorrectionStatus::REJECTED)
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('history.remark')
                            ->label('执行修改审计记录')
                            ->visible(fn (VideoCorrection $record) => $record->status === VideoCorrectionStatus::APPROVED)
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

                Tables\Columns\ImageColumn::make('video.list_img')
                    ->label('视频')
                    ->circular(false)
                    ->width('80px')
                    ->height('48px')
                    ->extraImgAttributes(['class' => 'rounded object-cover']),

                Tables\Columns\TextColumn::make('video.name')
                    ->label('关联视频')
                    ->description(fn (VideoCorrection $record) => $record->video ? "编号: {$record->video->video_code}" : '')
                    ->url(fn (VideoCorrection $record) => $record->video_id ? VideoResource::getUrl('view', ['record' => $record->video_id]) : null, true)
                    ->searchable()
                    ->limit(28),

                Tables\Columns\TextColumn::make('type')
                    ->label('修正维度')
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn ($state) => app(VideoCorrectionAuditService::class)->getTypeLabel($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('diff_summary')
                    ->label('修正建议摘要')
                    ->state(fn (VideoCorrection $record) => self::getShortPayloadPreview($record))
                    ->limit(32)
                    ->tooltip(fn (VideoCorrection $record) => self::getShortPayloadPreview($record)),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('提交人')
                    ->default(fn (VideoCorrection $record) => "用户 #{$record->user_id}")
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('审核状态')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof VideoCorrectionStatus ? $state->label() : VideoCorrectionStatus::tryFrom((int)$state)?->label() ?? '未知')
                    ->color(fn ($state) => match ($state instanceof VideoCorrectionStatus ? $state : VideoCorrectionStatus::tryFrom((int)$state)) {
                        VideoCorrectionStatus::PENDING  => 'warning',
                        VideoCorrectionStatus::APPROVED => 'success',
                        VideoCorrectionStatus::REJECTED => 'danger',
                        default                         => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('reviewer.name')
                    ->label('审核人')
                    ->default('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('reviewed_at')
                    ->label('审核时间')
                    ->dateTime('Y-m-d H:i')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('提交时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('审核状态')
                    ->options([
                        VideoCorrectionStatus::PENDING->value  => '待审核',
                        VideoCorrectionStatus::APPROVED->value => '已通过',
                        VideoCorrectionStatus::REJECTED->value => '已拒绝',
                    ]),

                Tables\Filters\SelectFilter::make('type')
                    ->label('修正维度')
                    ->options([
                        'name_zh'     => '中文标题',
                        'description' => '视频简介',
                        'actors'      => '参演演员',
                        'categories'  => '视频分类',
                        'tags'        => '标签',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('audit')
                    ->label('审核处理')
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->visible(fn (VideoCorrection $record) => $record->status === VideoCorrectionStatus::PENDING)
                    ->modalHeading('审核资料修正并执行修改')
                    ->modalSubmitActionLabel('确认审核结论')
                    ->modalWidth('4xl')
                    ->form(function (VideoCorrection $record): array {
                        return [
                            Forms\Components\Placeholder::make('diff_preview')
                                ->label('修改前与修改后数据比对')
                                ->content(new HtmlString(self::renderDiffHtml($record)))
                                ->columnSpanFull(),

                            Forms\Components\Radio::make('audit_decision')
                                ->label('审核结论')
                                ->options([
                                    'approve' => '✅ 审核通过（立即同步修改视频资料，并生成审计历史记录）',
                                    'reject'  => '❌ 驳回修正建议（不修改数据，记录驳回原因）',
                                ])
                                ->default('approve')
                                ->reactive()
                                ->required()
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('admin_remark')
                                ->label('审核批注 / 变更备注')
                                ->placeholder('例如：经核实无误，采纳用户建议补充演员 / 中文标题')
                                ->visible(fn (Forms\Get $get) => $get('audit_decision') === 'approve')
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('contrib_reward')
                                ->label('奖励用户贡献值')
                                ->numeric()
                                ->default(10)
                                ->suffix('点')
                                ->visible(fn (Forms\Get $get) => $get('audit_decision') === 'approve'),

                            Forms\Components\Textarea::make('reject_reason')
                                ->label('驳回原因')
                                ->placeholder('请填写拒绝原因，如：信息与官方源站不符、已存在相关内容等')
                                ->rows(3)
                                ->required(fn (Forms\Get $get) => $get('audit_decision') === 'reject')
                                ->visible(fn (Forms\Get $get) => $get('audit_decision') === 'reject')
                                ->columnSpanFull(),
                        ];
                    })
                    ->action(function (VideoCorrection $record, array $data, VideoCorrectionAuditService $auditService): void {
                        $adminUser = auth()->user();
                        $decision = $data['audit_decision'] ?? 'approve';

                        try {
                            if ($decision === 'approve') {
                                $adminRemark = $data['admin_remark'] ?? null;
                                $reward = (int) ($data['contrib_reward'] ?? 10);
                                $history = $auditService->approve($record, $adminUser, $adminRemark, $reward);

                                Notification::make()
                                    ->title('资料修正已通过并应用！')
                                    ->body("视频已更新完毕，已生成变更审计日志记录 (ID: #{$history->id})。")
                                    ->success()
                                    ->send();
                            } else {
                                $reason = $data['reject_reason'] ?? '审核未通过';
                                $auditService->reject($record, $adminUser, $reason);

                                Notification::make()
                                    ->title('已驳回该条资料修正申请')
                                    ->warning()
                                    ->send();
                            }
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('审核处理失败')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVideoCorrections::route('/'),
            'view'  => Pages\ViewVideoCorrection::route('/{record}'),
        ];
    }

    /**
     * 渲染新旧数据左右对比的 HTML 视图
     */
    public static function renderDiffHtml(VideoCorrection $record): HtmlString
    {
        $video = $record->video;
        if (!$video) {
            return new HtmlString('<div class="text-sm text-gray-500">关联视频已不存在</div>');
        }

        $type = $record->type;
        $oldDisplay = '';
        $newDisplay = '';

        if ($type === 'actors') {
            $oldActors = $video->actors()->pluck('name')->toArray();
            $oldDisplay = !empty($oldActors)
                ? implode('', array_map(fn($a) => "<span class='inline-block px-2 py-0.5 mr-1 mb-1 text-xs rounded bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'>{$a}</span>", $oldActors))
                : '<span class="text-gray-400 italic">暂无关联演员</span>';

            $newIds = (array) $record->formatted_payload;
            $newActors = Actor::whereIn('id', $newIds)->pluck('name')->toArray();
            $newDisplay = !empty($newActors)
                ? implode('', array_map(fn($a) => "<span class='inline-block px-2 py-0.5 mr-1 mb-1 text-xs rounded bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300 font-medium'>{$a}</span>", $newActors))
                : '<span class="text-gray-400 italic">建议清空演员</span>';
        } elseif ($type === 'categories') {
            $oldCats = $video->categories()->pluck('name')->toArray();
            $oldDisplay = !empty($oldCats)
                ? implode('', array_map(fn($c) => "<span class='inline-block px-2 py-0.5 mr-1 mb-1 text-xs rounded bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'>{$c}</span>", $oldCats))
                : '<span class="text-gray-400 italic">暂无分类</span>';

            $newIds = (array) $record->formatted_payload;
            $newCats = Category::whereIn('id', $newIds)->pluck('name')->toArray();
            $newDisplay = !empty($newCats)
                ? implode('', array_map(fn($c) => "<span class='inline-block px-2 py-0.5 mr-1 mb-1 text-xs rounded bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-300 font-medium'>{$c}</span>", $newCats))
                : '<span class="text-gray-400 italic">建议清空分类</span>';
        } elseif ($type === 'tags') {
            $oldTags = $video->tags()->pluck('name')->toArray();
            $oldDisplay = !empty($oldTags)
                ? implode('', array_map(fn($t) => "<span class='inline-block px-2 py-0.5 mr-1 mb-1 text-xs rounded bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'>#{$t}</span>", $oldTags))
                : '<span class="text-gray-400 italic">暂无标签</span>';

            $newIds = (array) $record->formatted_payload;
            $newTags = Tag::whereIn('id', $newIds)->pluck('name')->toArray();
            $newDisplay = !empty($newTags)
                ? implode('', array_map(fn($t) => "<span class='inline-block px-2 py-0.5 mr-1 mb-1 text-xs rounded bg-purple-100 text-purple-800 dark:bg-purple-900/60 dark:text-purple-300 font-medium'>#{$t}</span>", $newTags))
                : '<span class="text-gray-400 italic">建议清空标签</span>';
        } elseif (in_array($type, ['name_zh', 'title_cn'], true)) {
            $oldVal = htmlspecialchars($video->name_zh ?: '(暂无中文标题)', ENT_QUOTES);
            $newVal = htmlspecialchars((string) $record->payload, ENT_QUOTES);
            $oldDisplay = "<p class='text-sm text-gray-700 dark:text-gray-300'>{$oldVal}</p>";
            $newDisplay = "<p class='text-sm font-semibold text-emerald-700 dark:text-emerald-400'>{$newVal}</p>";
        } elseif ($type === 'description') {
            $oldVal = nl2br(htmlspecialchars($video->detail?->description ?: '(暂无视频简介)', ENT_QUOTES));
            $newVal = nl2br(htmlspecialchars((string) $record->payload, ENT_QUOTES));
            $oldDisplay = "<div class='text-xs text-gray-600 dark:text-gray-400 max-h-40 overflow-y-auto leading-relaxed'>{$oldVal}</div>";
            $newDisplay = "<div class='text-xs text-emerald-800 dark:text-emerald-300 font-medium max-h-40 overflow-y-auto leading-relaxed'>{$newVal}</div>";
        } else {
            $oldDisplay = '<span class="text-gray-400">-</span>';
            $newDisplay = htmlspecialchars((string) $record->payload, ENT_QUOTES);
        }

        $typeLabel = app(VideoCorrectionAuditService::class)->getTypeLabel($type);

        $html = "
        <div class='grid grid-cols-1 md:grid-cols-2 gap-4 my-2'>
            <div class='p-3.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-800/60'>
                <div class='text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2 flex items-center gap-1.5'>
                    <span class='w-2 h-2 rounded-full bg-gray-400'></span>
                    当前库内数据 (修改前 - {$typeLabel})
                </div>
                <div class='min-h-[48px]'>{$oldDisplay}</div>
            </div>
            <div class='p-3.5 rounded-lg border border-emerald-300 dark:border-emerald-700/60 bg-emerald-50/50 dark:bg-emerald-950/20'>
                <div class='text-xs font-semibold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider mb-2 flex items-center gap-1.5'>
                    <span class='w-2 h-2 rounded-full bg-emerald-500'></span>
                    用户修正建议 (生效后 - {$typeLabel})
                </div>
                <div class='min-h-[48px]'>{$newDisplay}</div>
            </div>
        </div>
        ";

        return new HtmlString($html);
    }

    /**
     * 获取修正载荷简略摘要文本
     */
    protected static function getShortPayloadPreview(VideoCorrection $record): string
    {
        $type = $record->type;
        if (in_array($type, ['actors', 'categories', 'tags'], true)) {
            $ids = (array) $record->formatted_payload;
            if (empty($ids)) {
                return '建议清空';
            }
            if ($type === 'actors') {
                return implode(', ', Actor::whereIn('id', $ids)->pluck('name')->toArray());
            }
            if ($type === 'categories') {
                return implode(', ', Category::whereIn('id', $ids)->pluck('name')->toArray());
            }
            if ($type === 'tags') {
                return implode(', ', Tag::whereIn('id', $ids)->pluck('name')->toArray());
            }
        }

        return (string) $record->payload;
    }
}

