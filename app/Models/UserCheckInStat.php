<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use RuntimeException;

class UserCheckInStat extends Model
{
    use HasFactory;

    /**
     * 主键字段（与用户表 ID 保持 1:1 映射）
     */
    protected $primaryKey = 'user_id';

    /**
     * 主键非自增
     */
    public $incrementing = false;

    /**
     * 允许批量赋值的属性白名单
     */
    protected $fillable = [
        'user_id',
        'last_check_in_date',
        'continuous_days',
        'longest_continuous_days',
        'total_days',
        'make_up_cards',
        'total_make_up_cards_earned',
        'month_make_up_count',
    ];

    /**
     * 属性原生类型转换
     */
    protected $casts = [
        'last_check_in_date' => 'date:Y-m-d',
        'continuous_days' => 'integer',
        'longest_continuous_days' => 'integer',
        'total_days' => 'integer',
        'make_up_cards' => 'integer',
        'total_make_up_cards_earned' => 'integer',
        'month_make_up_count' => 'integer',
    ];

    /**
     * 关联所属用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 关联补签卡变动流水明细
     */
    public function cardLogs(): HasMany
    {
        return $this->hasMany(UserCheckInCardLog::class, 'user_id', 'user_id');
    }

    /**
     * 增加补签卡并自动记录变动流水日志
     *
     * @param int $count 增加的卡片数量（必须为正整数）
     * @param string $action 变动动作类型标识（如 milestone_reward, admin_manual_grant）
     * @param string $description 变动说明
     * @param Model|null $source 关联的来源业务模型（如 UserCheckIn 记录）
     * @return UserCheckInCardLog 生成的流水记录
     * @throws InvalidArgumentException
     */
    public function addCards(int $count, string $action, string $description, ?Model $source = null): UserCheckInCardLog
    {
        if ($count <= 0) {
            throw new InvalidArgumentException('增加的补签卡数量必须大于 0');
        }

        $before = (int) $this->make_up_cards;
        $after = $before + $count;

        // 1. 更新可用卡与历史累计总数
        $this->make_up_cards = $after;
        $this->total_make_up_cards_earned += $count;
        $this->save();

        // 2. 写入审计流水
        return UserCheckInCardLog::create([
            'user_id' => $this->user_id,
            'change_count' => $count,
            'balance_before' => $before,
            'balance_after' => $after,
            'action' => $action,
            'description' => $description,
            'source_type' => $source ? get_class($source) : null,
            'source_id' => $source?->getKey(),
        ]);
    }

    /**
     * 扣除补签卡并自动自增当月补签计数与记录流水
     *
     * @param string $action 变动动作类型标识（如 make_up_checkin）
     * @param string $description 变动说明
     * @param Model|null $source 关联的来源业务模型
     * @return UserCheckInCardLog 生成的流水记录
     * @throws RuntimeException
     */
    public function consumeCard(string $action, string $description, ?Model $source = null): UserCheckInCardLog
    {
        if ($this->make_up_cards < 1) {
            throw new RuntimeException('当前持有的补签卡数量不足。');
        }

        $before = (int) $this->make_up_cards;
        $after = $before - 1;

        // 1. 扣除 1 张卡并累加当月已补签次数
        $this->make_up_cards = $after;
        $this->month_make_up_count += 1;
        $this->save();

        // 2. 写入审计流水
        return UserCheckInCardLog::create([
            'user_id' => $this->user_id,
            'change_count' => -1,
            'balance_before' => $before,
            'balance_after' => $after,
            'action' => $action,
            'description' => $description,
            'source_type' => $source ? get_class($source) : null,
            'source_id' => $source?->getKey(),
        ]);
    }
}
