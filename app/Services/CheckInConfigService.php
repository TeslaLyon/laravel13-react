<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;

class CheckInConfigService
{
    public const KEY_BASE_COINS = 'checkin:config:base_coins';
    public const KEY_MILESTONES = 'checkin:config:milestones';
    public const KEY_MAKE_UP_CONFIG = 'checkin:config:make_up';

    /**
     * 获取每日打卡基础金币
     */
    public function getBaseCoins(): int
    {
        $coins = Redis::get(self::KEY_BASE_COINS);
        if ($coins !== null) {
            return (int) $coins;
        }

        $default = (int) config('checkin.base_coins', 10);
        $this->setBaseCoins($default);
        return $default;
    }

    /**
     * 获取阶梯里程碑原始配置字典 (从 Redis 读取)
     *
     * @return array<int|string, array{coins: int, cards: int, title: string, icon: string}>
     */
    public function getMilestones(): array
    {
        $json = Redis::get(self::KEY_MILESTONES);
        if ($json) {
            $data = json_decode($json, true);
            if (is_array($data)) {
                return $data;
            }
        }

        $default = (array) config('checkin.milestones', []);
        $this->setMilestones($default);
        return $default;
    }

    /**
     * 根据指定月份动态解析里程碑配置
     * 自动将 'full_month' 映射为当月的实际总天数 (28/29/30/31 天)
     *
     * @param string|Carbon|null $date 目标月份日期 (如 '2026-08' 或 Carbon 实例)
     * @return array<int, array{coins: int, cards: int, title: string, icon: string, is_full_month?: bool}>
     */
    public function getMilestonesForMonth(string|Carbon|null $date = null): array
    {
        $month = $date ? Carbon::parse($date) : Carbon::today();
        $daysInMonth = $month->daysInMonth; // 动态计算 28, 29, 30 或 31

        $rawMilestones = $this->getMilestones();
        $resolved = [];

        foreach ($rawMilestones as $key => $config) {
            // 匹配 full_month 键，动态映射为该月总天数
            if ($key === 'full_month' || (string) $key === 'full_month') {
                $resolved[$daysInMonth] = array_merge($config, [
                    'title' => "满月全勤王者({$daysInMonth}天)",
                    'is_full_month' => true,
                ]);
            } else {
                $resolved[(int) $key] = $config;
            }
        }

        // 按天数由小到大升序排列
        ksort($resolved);
        return $resolved;
    }

    /**
     * 获取补签策略配置
     *
     * @return array{enabled: bool, max_per_month: int, initial_gift_cards: int, max_days_limit?: int}
     */
    public function getMakeUpConfig(): array
    {
        $json = Redis::get(self::KEY_MAKE_UP_CONFIG);
        if ($json) {
            $data = json_decode($json, true);
            if (is_array($data)) {
                return $data;
            }
        }

        // 未命中或数据损坏时，从 config 获取并重新缓存
        return $this->reloadMakeUpConfigFromConfigFile();
    }

    /**
     * 强制从配置文件重新加载并回写 Redis
     */
    public function reloadMakeUpConfigFromConfigFile(): array
    {
        $default = (array) config('checkin.make_up', [
            'enabled' => true,
            'max_per_month' => 3,
            'initial_gift_cards' => 1,
            'max_days_limit' => 30,
        ]);

        $this->setMakeUpConfig($default);

        return $default;
    }

    /**
     * 写入 Redis 配置
     *
     * @param array $config 配置内容
     * @param int|null $ttl 建议设置过期时间（如 86400 秒/1天），防止产生无法清理的死缓存
     */
    public function setMakeUpConfig(array $config, ?int $ttl = 86400): void
    {
        $payload = json_encode($config, JSON_UNESCAPED_UNICODE);

        if ($ttl) {
            Redis::setex(self::KEY_MAKE_UP_CONFIG, $ttl, $payload);
        } else {
            Redis::set(self::KEY_MAKE_UP_CONFIG, $payload);
        }
    }

    /**
     * 清除 Redis 配置缓存
     */
    public function clearMakeUpConfigCache(): void
    {
        Redis::del(self::KEY_MAKE_UP_CONFIG);
    }

    /**
     * 设置每日基础金币至 Redis
     */
    public function setBaseCoins(int $coins): void
    {
        Redis::set(self::KEY_BASE_COINS, $coins);
    }

    /**
     * 设置里程碑配置至 Redis
     */
    public function setMilestones(array $milestones): void
    {
        Redis::set(self::KEY_MILESTONES, json_encode($milestones, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 将 config/checkin.php 配置文件内容一次性同步推送到 Redis
     */
    public function syncFromConfigFile(): void
    {
        $this->setBaseCoins((int) config('checkin.base_coins', 10));
        $this->setMilestones((array) config('checkin.milestones', []));
        $this->setMakeUpConfig((array) config('checkin.make_up', []));
    }
}
