<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Services\CreditService;
use App\Services\UserAvatarService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\HtmlString;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationGroup = '用户管理';

    protected static ?string $modelLabel = '用户';

    protected static ?string $pluralModelLabel = '用户管理';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 1;

    /**
     * 规范化媒体图片 URL (兼容完整 URL、CDN 与本地相对路径)
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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('账户基础信息')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('用户名')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('nickname')
                            ->label('昵称')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label('电子邮箱')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('password')
                            ->label('登录密码')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->placeholder('编辑时留空表示不修改密码'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('头像与违规积分')
                    ->schema([
                        Forms\Components\Placeholder::make('avatar_preview')
                            ->label('当前头像预览')
                            ->content(function (?User $record): HtmlString {
                                $url = static::resolveMediaUrl($record?->avatar) ?: asset(config('avatars.fallback'));
                                $hasCustom = !empty($record?->avatar);
                                $statusTag = $hasCustom
                                    ? '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300">自定义头像</span>'
                                    : '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300">未设置/系统默认</span>';

                                return new HtmlString("
                                    <div class=\"flex items-center gap-4\">
                                        <img src=\"{$url}\" alt=\"头像\" class=\"w-16 h-16 rounded-full object-cover border-2 border-gray-200 dark:border-gray-700 shadow-sm\" />
                                        <div class=\"flex flex-col gap-1\">
                                            <div>{$statusTag}</div>
                                            <span class=\"text-xs text-gray-400 font-mono\">" . e($record?->avatar ?: '（无独立文件）') . "</span>
                                        </div>
                                    </div>
                                ");
                            }),

                        Forms\Components\TextInput::make('avatar')
                            ->label('头像路径 / URL')
                            ->placeholder('支持图片相对路径如 /images/default-avatars/avatar-1.svg 或完整 URL')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('violation_count')
                            ->label('违规次数 (violation_count)')
                            ->numeric()
                            ->default(0)
                            ->helperText('用户历史违规次数，每次违规在计算综合总积分时扣减 20 分'),

                        Forms\Components\TextInput::make('total_credits')
                            ->label('综合总积分 (total_credits)')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('由活跃指标与违规惩罚自动实时计算得出'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\ImageColumn::make('avatar')
                    ->label('头像')
                    ->circular()
                    ->size(40)
                    ->checkFileExistence(false)
                    ->state(fn (User $record): ?string => static::resolveMediaUrl($record->avatar))
                    ->defaultImageUrl(fn () => asset(config('avatars.fallback'))),

                Tables\Columns\TextColumn::make('name')
                    ->label('用户名')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('nickname')
                    ->label('昵称')
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('邮箱')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('violation_count')
                    ->label('违规次数')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => (int) $state > 0 ? 'danger' : 'gray'),

                Tables\Columns\TextColumn::make('total_credits')
                    ->label('总积分')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('注册时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\Filter::make('has_custom_avatar')
                    ->label('已设置自定义头像')
                    ->query(fn (Builder $query) => $query->whereNotNull('avatar')->where('avatar', '!=', '')),

                Tables\Filters\Filter::make('has_violations')
                    ->label('存在违规记录 (>0)')
                    ->query(fn (Builder $query) => $query->where('violation_count', '>', 0)),
            ])
            ->actions([
                Tables\Actions\Action::make('takeDownAvatar')
                    ->label('下架头像')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('danger')
                    ->modalHeading('审核处理违规头像')
                    ->modalDescription('下架当前违规头像，可指定替换为系统预设默认头像，并选择性添加违规记录扣减积分。')
                    ->modalSubmitActionLabel('确认下架处理')
                    ->form([
                        Forms\Components\Placeholder::make('target_user_info')
                            ->label('目标用户')
                            ->content(function (User $record): HtmlString {
                                $url = static::resolveMediaUrl($record->avatar) ?: asset(config('avatars.fallback'));
                                return new HtmlString("
                                    <div class=\"p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 flex items-center gap-4\">
                                        <img src=\"{$url}\" alt=\"当前头像\" class=\"w-14 h-14 rounded-full object-cover border-2 border-red-500 shadow\" />
                                        <div class=\"text-xs space-y-1\">
                                            <div><strong>用户:</strong> {$record->name} (" . e($record->nickname ?: '无昵称') . ")</div>
                                            <div><strong>邮箱:</strong> {$record->email}</div>
                                            <div><strong>当前违规次数:</strong> <span class=\"text-red-500 font-bold\">{$record->violation_count}</span> | <strong>当前积分:</strong> <span class=\"text-amber-500 font-bold\">{$record->total_credits}</span></div>
                                        </div>
                                    </div>
                                ");
                            }),

                        Forms\Components\Select::make('replacement_avatar')
                            ->label('替换为默认头像')
                            ->options(fn (UserAvatarService $avatarService) => $avatarService->getDefaultAvatarSelectOptions())
                            ->default(config('avatars.fallback'))
                            ->required()
                            ->helperText('选择下架后用户显示的新头像。您可以随时在 config/avatars.php 中配置更多默认头像'),

                        Forms\Components\Toggle::make('record_violation')
                            ->label('记录违规行为并扣除总积分')
                            ->default(true)
                            ->live()
                            ->helperText('开启后自动给用户增加违规记录，并按照系统惩罚规则实时扣减总积分（每 1 次扣除 20 分）'),

                        Forms\Components\TextInput::make('violation_amount')
                            ->label('增加违规次数')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(10)
                            ->visible(fn (Forms\Get $get) => (bool) $get('record_violation'))
                            ->required(fn (Forms\Get $get) => (bool) $get('record_violation'))
                            ->helperText('每增加 1 次违规扣除 20 积分，并同步记入用户积分变动审计流水'),

                        Forms\Components\Textarea::make('reason')
                            ->label('违规原因 / 处理备注')
                            ->default('头像包含涉嫌违规内容，已下架处理')
                            ->rows(2)
                            ->required(),

                        Forms\Components\Toggle::make('delete_old_file')
                            ->label('物理删除服务器旧文件')
                            ->default(true)
                            ->helperText('若旧头像是上传在服务器本地的文件，勾选后将物理删除，防止外链盗链访问'),
                    ])
                    ->action(function (User $record, array $data, UserAvatarService $avatarService) {
                        $result = $avatarService->takeDownAvatar(
                            user: $record,
                            replacementAvatarUrl: $data['replacement_avatar'] ?? null,
                            recordViolation: (bool) ($data['record_violation'] ?? false),
                            violationAmount: (int) ($data['violation_amount'] ?? 1),
                            reason: (string) ($data['reason'] ?? '头像涉嫌违规下架'),
                            deleteOldFile: (bool) ($data['delete_old_file'] ?? true),
                            operatorId: auth()->id()
                        );

                        $msg = "用户 [{$record->name}] 的违规头像已成功下架！";
                        if (!empty($result['violation_recorded'])) {
                            $msg .= " 已增加违规记录，当前违规次数：{$result['violation_count']}，扣减后最新总积分：{$result['total_credits']}";
                        }

                        Notification::make()
                            ->title('违规头像处理成功')
                            ->body($msg)
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('batchTakeDownAvatar')
                        ->label('批量下架违规头像')
                        ->icon('heroicon-o-shield-exclamation')
                        ->color('danger')
                        ->modalHeading('批量下架选中用户的头像')
                        ->modalDescription('批量将选中用户的头像重置为系统默认头像，并可选择是否批量计入违规记录。')
                        ->modalSubmitActionLabel('确认批量下架')
                        ->form([
                            Forms\Components\Select::make('replacement_avatar')
                                ->label('替换为默认头像')
                                ->options(fn (UserAvatarService $avatarService) => $avatarService->getDefaultAvatarSelectOptions())
                                ->default(config('avatars.fallback'))
                                ->required(),

                            Forms\Components\Toggle::make('record_violation')
                                ->label('记录违规行为并扣除总积分')
                                ->default(true)
                                ->live()
                                ->helperText('开启后对选中的每位用户增加违规记录并按规则扣除积分'),

                            Forms\Components\TextInput::make('violation_amount')
                                ->label('每人增加违规次数')
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->maxValue(10)
                                ->visible(fn (Forms\Get $get) => (bool) $get('record_violation'))
                                ->required(fn (Forms\Get $get) => (bool) $get('record_violation')),

                            Forms\Components\Textarea::make('reason')
                                ->label('违规原因 / 处理备注')
                                ->default('头像包含涉嫌违规内容，已批量下架处理')
                                ->rows(2)
                                ->required(),

                            Forms\Components\Toggle::make('delete_old_file')
                                ->label('物理删除服务器旧文件')
                                ->default(true),
                        ])
                        ->action(function (Collection $records, array $data, UserAvatarService $avatarService) {
                            $count = 0;
                            foreach ($records as $user) {
                                $avatarService->takeDownAvatar(
                                    user: $user,
                                    replacementAvatarUrl: $data['replacement_avatar'] ?? null,
                                    recordViolation: (bool) ($data['record_violation'] ?? false),
                                    violationAmount: (int) ($data['violation_amount'] ?? 1),
                                    reason: (string) ($data['reason'] ?? '批量下架头像'),
                                    deleteOldFile: (bool) ($data['delete_old_file'] ?? true),
                                    operatorId: auth()->id()
                                );
                                $count++;
                            }

                            Notification::make()
                                ->title('批量处理完成')
                                ->body("已成功批量下架 {$count} 位用户的头像并完成审核处理。")
                                ->success()
                                ->send();
                        }),

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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
