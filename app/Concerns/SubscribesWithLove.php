<?php

namespace App\Concerns;

use Cog\Contracts\Love\Reactable\Models\Reactable as ReactableInterface;
use Cog\Laravel\Love\ReactionType\Models\ReactionType;

/**
 * 通用订阅与通知级别业务 Trait
 *
 * @mixin \Cog\Laravel\Love\Reacterable\Models\Traits\HasLoveReacter
 */
trait SubscribesWithLove
{
    /**
     * 订阅通知级别与 Laravel Love 反应类型映射
     */
    protected static array $subscriptionReactionTypes = [
        'all' => 'SubscribeAll',
        'personalized' => 'SubscribePersonalized', // 默认级别
        'none' => 'SubscribeNone',
    ];

    /**
     * 订阅目标实体并设定通知级别 (支持 User / Actor / Category / Tag 等)
     *
     * @param ReactableInterface $target 被订阅的实体
     * @param string $notificationType 通知类型 ('all' | 'personalized' | 'none')
     */
    public function subscribeToEntity(ReactableInterface $target, string $notificationType = 'personalized'): void
    {
        $reacterFacade = $this->viaLoveReacter();
        $targetReactionName = self::$subscriptionReactionTypes[$notificationType] ?? 'SubscribePersonalized';

        // 1. 互斥清理：先移除该目标上可能已存在的旧订阅状态
        foreach (self::$subscriptionReactionTypes as $reactionName) {
            if ($reacterFacade->hasReactedTo($target, $reactionName)) {
                $reacterFacade->unreactTo($target, $reactionName);
            }
        }

        // 2. 赋予新的订阅级别
        $reacterFacade->reactTo($target, $targetReactionName);
    }

    /**
     * 取消订阅目标实体
     */
    public function unsubscribeFromEntity(ReactableInterface $target): void
    {
        $reacterFacade = $this->viaLoveReacter();

        foreach (self::$subscriptionReactionTypes as $reactionName) {
            if ($reacterFacade->hasReactedTo($target, $reactionName)) {
                $reacterFacade->unreactTo($target, $reactionName);
            }
        }
    }

    /**
     * 检查当前用户是否已订阅目标实体
     */
    public function hasSubscribedToEntity(ReactableInterface $target): bool
    {
        $reacterFacade = $this->viaLoveReacter();

        foreach (self::$subscriptionReactionTypes as $reactionName) {
            if ($reacterFacade->hasReactedTo($target, $reactionName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取当前对目标实体的通知偏好级别（未订阅返回 null）
     */
    public function getSubscriptionNotificationType(ReactableInterface $target): ?string
    {
        $reacterFacade = $this->viaLoveReacter();

        foreach (self::$subscriptionReactionTypes as $typeKey => $reactionName) {
            if ($reacterFacade->hasReactedTo($target, $reactionName)) {
                return $typeKey; // 返回 'all' | 'personalized' | 'none'
            }
        }

        return null;
    }

    /**
     * 聚合计算实体的总订阅者（粉丝）数
     */
    public function getEntitySubscribersCount(ReactableInterface $target): int
    {
        $reactant = $target->getLoveReactant();
        if (!$reactant) {
            return 0;
        }

        $total = 0;
        foreach (self::$subscriptionReactionTypes as $reactionName) {
            // 🎯 关键修复点：使用 ReactionType::fromName 获取对象实例
            $reactionType = ReactionType::fromName($reactionName);
            $total += (int) ($reactant->getReactionCounterOfType($reactionType)?->getCount() ?? 0);
        }

        return $total;
    }
}
