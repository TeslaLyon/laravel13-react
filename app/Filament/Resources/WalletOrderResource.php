<?php

namespace App\Filament\Resources;

use App\Enums\TransactionType;
use App\Filament\Resources\WalletOrderResource\Pages;
use App\Jobs\SendTelegramMessageJob;
use App\Models\User;
use App\Models\WalletOrder;
use App\Notifications\WalletRechargeNotification;
use App\Services\WalletService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class WalletOrderResource extends Resource
{
    protected static ?string $model = WalletOrder::class;

    protected static ?string $navigationGroup = '财务与钱包';

    protected static ?string $modelLabel = '充值订单';

    protected static ?string $pluralModelLabel = '充值订单';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $count = WalletOrder::where('payment_method', WalletOrder::METHOD_ALIPAY_RED_PACKET)
            ->where('status', WalletOrder::STATUS_PENDING)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('订单信息')
                    ->schema([
                        Forms\Components\TextInput::make('order_no')
                            ->label('商户订单号')
                            ->disabled(),

                        Forms\Components\TextInput::make('payment_method')
                            ->label('支付渠道')
                            ->formatStateUsing(fn (?string $state) => match ($state) {
                                WalletOrder::METHOD_ALIPAY_RED_PACKET => '支付宝口令红包',
                                WalletOrder::METHOD_ALIPAY            => '支付宝',
                                WalletOrder::METHOD_WECHAT            => '微信支付',
                                default                               => (string) $state,
                            })
                            ->disabled(),

                        Forms\Components\TextInput::make('red_packet_code')
                            ->label('支付宝红包口令')
                            ->disabled(),

                        Forms\Components\TextInput::make('amount')
                            ->label('申报金额 (元)')
                            ->formatStateUsing(fn ($state) => number_format(((int) $state) / 100, 2, '.', ''))
                            ->disabled(),

                        Forms\Components\TextInput::make('really_amount')
                            ->label('实际入账金额 (元)')
                            ->formatStateUsing(fn ($state) => number_format(((int) $state) / 100, 2, '.', ''))
                            ->disabled(),

                        Forms\Components\Textarea::make('admin_remark')
                            ->label('管理员审核备注')
                            ->columnSpanFull()
                            ->disabled(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('order_no')
                    ->label('订单号')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('订单号已复制')
                    ->fontFamily('mono')
                    ->size('xs'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('申请用户')
                    ->formatStateUsing(function ($state, WalletOrder $record): string {
                        $user = $record->user;
                        if (!$user) {
                            return "User #{$record->user_id}";
                        }
                        $name = $user->nickname ?: $user->name;
                        return "{$name} (ID:{$user->id})";
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('充值方式')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        WalletOrder::METHOD_ALIPAY_RED_PACKET => '支付宝口令红包',
                        WalletOrder::METHOD_ALIPAY            => '支付宝',
                        WalletOrder::METHOD_WECHAT            => '微信支付',
                        default                               => (string) $state,
                    })
                    ->color(fn (?string $state) => match ($state) {
                        WalletOrder::METHOD_ALIPAY_RED_PACKET => 'warning',
                        WalletOrder::METHOD_ALIPAY            => 'info',
                        WalletOrder::METHOD_WECHAT            => 'success',
                        default                               => 'gray',
                    }),

                Tables\Columns\TextColumn::make('red_packet_code')
                    ->label('红包口令 (点击复制)')
                    ->placeholder('-')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('红包口令已复制到剪贴板')
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('申报金额')
                    ->formatStateUsing(fn ($state) => '¥' . number_format(((int) $state) / 100, 2))
                    ->sortable(),

                Tables\Columns\TextColumn::make('really_amount')
                    ->label('实付入账')
                    ->formatStateUsing(fn ($state, WalletOrder $record) => $record->status === WalletOrder::STATUS_PAID
                        ? '¥' . number_format(((int) $state) / 100, 2)
                        : '-')
                    ->color('success')
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('status')
                    ->label('订单状态')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ((int) $state) {
                        WalletOrder::STATUS_PENDING => '待核实 / 待支付',
                        WalletOrder::STATUS_PAID    => '已到账',
                        WalletOrder::STATUS_CLOSED  => '已驳回 / 关闭',
                        default                     => '未知',
                    })
                    ->color(fn ($state) => match ((int) $state) {
                        WalletOrder::STATUS_PENDING => 'warning',
                        WalletOrder::STATUS_PAID    => 'success',
                        WalletOrder::STATUS_CLOSED  => 'danger',
                        default                     => 'gray',
                    }),

                Tables\Columns\TextColumn::make('admin_remark')
                    ->label('审核备注')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('提交时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('到账时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('订单状态')
                    ->options([
                        WalletOrder::STATUS_PENDING => '待核实 / 待支付',
                        WalletOrder::STATUS_PAID    => '已到账',
                        WalletOrder::STATUS_CLOSED  => '已驳回 / 关闭',
                    ]),

                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('充值方式')
                    ->options([
                        WalletOrder::METHOD_ALIPAY_RED_PACKET => '支付宝口令红包',
                        WalletOrder::METHOD_ALIPAY            => '支付宝',
                        WalletOrder::METHOD_WECHAT            => '微信支付',
                    ]),
            ])
            ->actions([
                // 1. 确认收款并向用户增加余额（仅限支付宝口令红包）
                Tables\Actions\Action::make('approve')
                    ->label('确认收款并加款')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (WalletOrder $record): bool => (int) $record->status === WalletOrder::STATUS_PENDING
                        && $record->payment_method === WalletOrder::METHOD_ALIPAY_RED_PACKET)
                    ->modalHeading('核实收款并为用户发放纸巾')
                    ->modalDescription('请确认您已在支付宝领取该口令红包，核对无误后输入实际收到的金额，提交后将自动入账并通知用户。')
                    ->form(fn (WalletOrder $record): array => [
                        Forms\Components\Placeholder::make('user_summary')
                            ->label('申请用户')
                            ->content(fn () => ($record->user?->nickname ?: $record->user?->name ?? "User #{$record->user_id}") . " (ID: {$record->user_id})"),

                        Forms\Components\Placeholder::make('red_packet_display')
                            ->label('支付宝红包口令')
                            ->content($record->red_packet_code ?: '无 (非口令红包渠道)'),

                        Forms\Components\TextInput::make('actual_amount_yuan')
                            ->label('实际核实到账金额 (元 / 纸巾)')
                            ->helperText('默认填入用户申报金额，若支付宝实际金额与申报不同，请按实际金额修改，系统将精准入账。')
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->default(number_format($record->amount / 100, 2, '.', ''))
                            ->required(),

                        Forms\Components\Textarea::make('admin_remark')
                            ->label('审核备注 (可选)')
                            ->default('支付宝口令红包核实通过')
                            ->rows(2)
                            ->maxLength(255),
                    ])
                    ->action(function (WalletOrder $record, array $data): void {
                        if ($record->payment_method !== WalletOrder::METHOD_ALIPAY_RED_PACKET) {
                            Notification::make()
                                ->title('操作被拒绝')
                                ->body('仅“支付宝口令红包”订单支持后台人工确认收款并加款')
                                ->danger()
                                ->send();
                            return;
                        }

                        $reallyAmountCents = (int) round(((float) $data['actual_amount_yuan']) * 100);
                        if ($reallyAmountCents <= 0) {
                            Notification::make()
                                ->title('入账金额必须大于 0')
                                ->danger()
                                ->send();
                            return;
                        }

                        $remark = trim((string) ($data['admin_remark'] ?? ''));

                        try {
                            $approvedOrder = DB::transaction(function () use ($record, $reallyAmountCents, $remark) {
                                /** @var WalletOrder $lockedOrder */
                                $lockedOrder = WalletOrder::where('id', $record->id)
                                    ->lockForUpdate()
                                    ->firstOrFail();

                                if ($lockedOrder->payment_method !== WalletOrder::METHOD_ALIPAY_RED_PACKET) {
                                    throw new \RuntimeException('仅“支付宝口令红包”订单允许人工确认收款并加款');
                                }

                                if ((int) $lockedOrder->status !== WalletOrder::STATUS_PENDING) {
                                    throw new \RuntimeException('该订单状态已变更，请勿重复审核放款');
                                }

                                $user = $lockedOrder->user ?? User::findOrFail($lockedOrder->user_id);
                                /** @var WalletService $walletService */
                                $walletService = app(WalletService::class);
                                $wallet = $walletService->getOrCreateWallet($user);

                                $tissuesFormatted = number_format($reallyAmountCents / 100, 2, '.', '');
                                $methodLabel = $lockedOrder->paymentMethodLabel();

                                // 原子加款并写入安全账本流水
                                $walletService->changeBalance(
                                    wallet: $wallet,
                                    amountCents: $reallyAmountCents,
                                    type: TransactionType::RECHARGE,
                                    description: "{$methodLabel}充值获取 {$tissuesFormatted} 纸巾",
                                    source: $lockedOrder,
                                    referenceId: $lockedOrder->order_no,
                                    metadata: [
                                        'order_no'        => $lockedOrder->order_no,
                                        'payment_method'  => $lockedOrder->payment_method,
                                        'red_packet_code' => $lockedOrder->red_packet_code,
                                        'order_amount'    => $lockedOrder->amount,
                                        'really_amount'   => $reallyAmountCents,
                                        'reviewed_by'     => Auth::id(),
                                        'admin_remark'    => $remark,
                                    ]
                                );

                                $lockedOrder->update([
                                    'status'        => WalletOrder::STATUS_PAID,
                                    'really_amount' => $reallyAmountCents,
                                    'paid_at'       => now(),
                                    'reviewed_by'   => Auth::id(),
                                    'reviewed_at'   => now(),
                                    'admin_remark'  => $remark ?: null,
                                ]);

                                return $lockedOrder;
                            });

                            // 1. 触发全站通知 + 队列邮件通知用户充值已到账
                            $targetUser = $approvedOrder->user;
                            if ($targetUser) {
                                $targetUser->notify(new WalletRechargeNotification(
                                    order: $approvedOrder,
                                    result: 'approved',
                                    actualAmountCents: $reallyAmountCents,
                                    remark: $remark ?: null
                                ));
                            }

                            // 2. 同步向 Telegram 发送回执，方便管理员团队留档与避免重复核验
                            $tissuesFormatted = number_format($reallyAmountCents / 100, 2, '.', '');
                            $tgMsg = "✅ <b>口令红包已核实并成功放款</b>\n"
                                . "🧾 订单编号：<code>" . e($approvedOrder->order_no) . "</code>\n"
                                . "💰 实付入账：¥{$tissuesFormatted} ({$tissuesFormatted} 纸巾)\n"
                                . "👤 审核管理员：" . e(Auth::user()?->name ?? 'Admin');
                            SendTelegramMessageJob::dispatch($tgMsg);

                            Notification::make()
                                ->title("入账成功！已为用户增加 {$tissuesFormatted} 纸巾并发送通知")
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('放款失败')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // 2. 驳回无效口令或未收到款项的订单
                Tables\Actions\Action::make('reject')
                    ->label('驳回订单')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (WalletOrder $record): bool => (int) $record->status === WalletOrder::STATUS_PENDING)
                    ->modalHeading('驳回充值申请')
                    ->form([
                        Forms\Components\Textarea::make('admin_remark')
                            ->label('驳回原因 (将展示给用户)')
                            ->placeholder('例如：口令无效、红包已被其他人领取、或未收到对应款项')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (WalletOrder $record, array $data): void {
                        $reason = trim((string) ($data['admin_remark'] ?? '未核实到款项'));

                        try {
                            $rejectedOrder = DB::transaction(function () use ($record, $reason) {
                                /** @var WalletOrder $lockedOrder */
                                $lockedOrder = WalletOrder::where('id', $record->id)
                                    ->lockForUpdate()
                                    ->firstOrFail();

                                if ((int) $lockedOrder->status !== WalletOrder::STATUS_PENDING) {
                                    throw new \RuntimeException('该订单状态已变更，无法执行驳回');
                                }

                                $lockedOrder->update([
                                    'status'       => WalletOrder::STATUS_CLOSED,
                                    'reviewed_by'  => Auth::id(),
                                    'reviewed_at'  => now(),
                                    'admin_remark' => $reason,
                                ]);

                                return $lockedOrder;
                            });

                            if ($rejectedOrder->user) {
                                $rejectedOrder->user->notify(new WalletRechargeNotification(
                                    order: $rejectedOrder,
                                    result: 'rejected',
                                    actualAmountCents: null,
                                    remark: $reason
                                ));
                            }

                            Notification::make()
                                ->title('已驳回该订单并通知用户')
                                ->warning()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('操作失败')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWalletOrders::route('/'),
        ];
    }
}
