<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use App\Notifications\QueuedVerifyEmail;
use App\Notifications\QueuedResetPassword;
use Cog\Contracts\Love\Reacterable\Models\Reacterable as ReacterableInterface;
use Cog\Contracts\Love\Reactable\Models\Reactable as ReactableInterface;
use Cog\Laravel\Love\Reacterable\Models\Traits\Reacterable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Cog\Laravel\Love\Reactable\Models\Traits\Reactable;
use App\Concerns\SubscribesWithLove;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $nickname
 * @property int|null $love_reacter_id
 * @property-read \Cog\Laravel\Love\Reacter\Models\Reacter|null $loveReacter
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Passkeys\Passkey> $passkeys
 * @property-read int|null $passkeys_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLoveReacterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereNickname($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorConfirmedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorRecoveryCodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class User extends Authenticatable implements PasskeyUser, MustVerifyEmail, ReacterableInterface, ReactableInterface
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable, Reacterable, Reactable, SubscribesWithLove;

    protected $fillable = [
        'name',
        'nickname',
        'email',
        'password',
        'avatar',
        'primary_group_id',
        'custom_title',
        'cached_title',
        'post_count',
        'thread_count',
        'reaction_score',
        'trophy_points',
        'enthusiasm_points',
        'bounty_points',
        'contribution_points',
        'prestige_points',
        'digest_thread_count',
        'violation_count',
        'total_credits',
        'last_activity_at',
        'banner_url',
        'avatar_decoration_id',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token'
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'primary_group_id' => 'integer',
            'post_count' => 'integer',
            'thread_count' => 'integer',
            'reaction_score' => 'integer',
            'trophy_points' => 'integer',
            'enthusiasm_points' => 'integer',
            'bounty_points' => 'integer',
            'contribution_points' => 'integer',
            'prestige_points' => 'integer',
            'digest_thread_count' => 'integer',
            'violation_count' => 'integer',
            'total_credits' => 'float',
            'last_activity_at' => 'datetime',
        ];
    }

    /**
     * 获取与用户关联的钱包记录 (1对1关系)
     */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    /**
     * 重写发送邮箱验证通知的方法
     */
    public function sendEmailVerificationNotification()
    {
        // 使用我们自定义的异步通知类
        $this->notify(new QueuedVerifyEmail);
    }

    /**
     * 重写发送密码重置通知的方法
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token)
    {
        // 使用我们自定义的异步通知类，并将 token 传给它
        $this->notify(new QueuedResetPassword($token));
    }

    /**
     * 用户获得的全部勋章关联记录
     */
    public function userMedals(): HasMany
    {
        return $this->hasMany(UserMedal::class, 'user_id');
    }

    /**
     * 用户获得的所有勋章对象
     */
    public function medals(): BelongsToMany
    {
        return $this->belongsToMany(Medal::class, 'user_medals')
            ->using(UserMedal::class)
            ->withPivot(['id', 'awarded_by_user_id', 'award_reason', 'is_worn', 'wear_slot', 'unlocked_at'])
            ->withTimestamps();
    }

    /**
     * 用户当前佩戴的勋章列表（按槽位排序）
     */
    public function wornMedals(): BelongsToMany
    {
        return $this->medals()
            ->wherePivot('is_worn', true)
            ->orderByPivot('wear_slot');
    }

    /**
     * 获取用户当前累计的总成就点数
     */
    public function getTotalTrophyPointsAttribute(): int
    {
        return (int) $this->medals()->sum('trophy_points');
    }

    /**
     * 1. 获取用户获得的所有勋章
     */
    // public function badges(): BelongsToMany
    // {
    //     return $this->belongsToMany(Badge::class)
    //         ->using(BadgeUser::class)
    //         ->where('badges.is_active', true)
    //         ->withPivot(['is_equipped', 'awarded_at', 'expires_at'])
    //         ->withTimestamps();
    // }

    /**
     * 🎯 获取用户佩戴且在有效期内的有效勋章
     */
    // public function equippedBadges(): BelongsToMany
    // {
    //     return $this->belongsToMany(Badge::class)
    //         ->using(BadgeUser::class)
    //         ->withPivot(['is_equipped', 'awarded_at', 'expires_at'])
    //         ->wherePivot('is_equipped', true)
    //         ->where(function ($query) {
    //             $query->whereNull('badge_user.expires_at')
    //                 ->orWhere('badge_user.expires_at', '>', now());
    //         })
    //         ->where('badges.is_active', true)
    //         ->orderBy('badges.display_order', 'asc');
    // }

    /**
     * 用户所属的主用户组（快捷关联）
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class, 'primary_group_id');
    }

    public function primaryGroup(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class, 'primary_group_id');
    }

    public function secondaryGroups(): BelongsToMany
    {
        return $this->belongsToMany(UserGroup::class, 'user_group_user')
            ->withPivot('is_displayed');
    }

    /**
     * 🎯 1. 积分加权公式计算引擎
     */
    public function calculateTotalCredits(): float
    {
        $score = ($this->post_count * 0.1)
            + ($this->enthusiasm_points * 1.2)
            + ($this->bounty_points * 1.5)
            + ($this->contribution_points * 1.5)
            + ($this->prestige_points * 20.0)
            + ($this->digest_thread_count * 100.0)
            - ($this->violation_count * 20.0);

        return max(0.00, round($score, 2));
    }

    /**
     * 🎯 2. 刷新总积分并联动刷新阶梯头衔
     */
    public function refreshTotalCredits(): void
    {
        $newCredits = $this->calculateTotalCredits();

        $this->total_credits = $newCredits;

        // 匹配阶梯头衔
        $matchedLadder = UserTitleLadder::findLadderForCredits($newCredits, (int) $this->post_count);
        $this->cached_title = $matchedLadder?->title;

        $this->saveQuietly();
    }

    /**
     * 🎯 3. 头衔展示文本：优先自定义头衔，其次为阶梯头衔
     */
    protected function displayTitle(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->custom_title ?: ($this->cached_title ?: '初入江湖'),
        );
    }

    /**
     * 🎯 4. 获取用户最高优先级的主样式与全部有效横幅列表 (XF 2.3 特性)
     */
    public function getRenderBanners(): Collection
    {
        $groups = collect();

        if ($this->primaryGroup && $this->primaryGroup->banner_text) {
            $groups->push($this->primaryGroup);
        }

        // 合并需展示的次要组
        $displayedSecondary = $this->secondaryGroups
            ->filter(fn($g) => (bool) $g->pivot->is_displayed && !empty($g->banner_text));

        return $groups->concat($displayedSecondary)
            ->sortByDesc('display_style_priority')
            ->values()
            ->map(fn(UserGroup $group) => [
                'name' => $group->banner_text,
                'bgColor' => $group->banner_bg_color,
                'textColor' => $group->banner_text_color,
                'icon' => $group->banner_icon,
            ]);
    }

    /**
     * 获取用户当前生效的头衔（优先使用自定义头衔，否则使用等级组头衔）
     */
    public function getDisplayTitleAttribute(): string
    {
        if (!empty($this->custom_title) && $this->canCustomTitle()) {
            return $this->custom_title;
        }

        return $this->cached_title ?: ($this->primaryGroup?->title ?? '注册会员');
    }

    /**
     * 权限判定：是否允许使用站内搜索
     */
    public function canSearch(): bool
    {
        return (bool) ($this->primaryGroup?->allow_search ?? false);
    }

    /**
     * 权限判定：是否允许使用自定义头衔
     */
    public function canCustomTitle(): bool
    {
        return (bool) ($this->primaryGroup?->allow_custom_title ?? false);
    }

    /**
     * 权限判定：是否满足阅读权限门槛
     */
    public function hasReadPermission(int $requiredLevel): bool
    {
        $userLevel = (int) ($this->primaryGroup?->read_permission_level ?? 0);
        return $userLevel >= $requiredLevel;
    }

    public function avatarDecoration(): BelongsTo
    {
        return $this->belongsTo(AvatarDecoration::class, 'avatar_decoration_id');
    }

    /**
     * 检查当前佩戴挂件是否依然有效（未过期且仍拥有）；若已过期则自动卸下并清理脏数据
     */
    public function cleanIfDecorationExpired(): bool
    {
        if ($this->avatar_decoration_id === null) {
            return false;
        }

        $isValid = $this->unlockedDecorations()
            ->where('avatar_decorations.id', $this->avatar_decoration_id)
            ->where(function ($query) {
                $query->whereNull('user_avatar_decorations.expires_at')
                    ->orWhere('user_avatar_decorations.expires_at', '>', \Illuminate\Support\Carbon::now());
            })
            ->exists();

        if (!$isValid) {
            $this->update(['avatar_decoration_id' => null]);
            $this->avatar_decoration_id = null;
            $this->setRelation('avatarDecoration', null);
            return false;
        }

        return true;
    }

    public function unlockedDecorations(): BelongsToMany
    {
        return $this->belongsToMany(AvatarDecoration::class, 'user_avatar_decorations')
            ->using(UserAvatarDecoration::class) // 🌟 声明使用专属 Pivot 模型
            ->withPivot(['unlocked_at', 'expires_at'])
            ->withTimestamps();
    }

    /**
     * 用户独立隐私配置关联（1 对 1）
     */
    public function privacySetting(): HasOne
    {
        return $this->hasOne(UserPrivacySetting::class);
    }

    /**
     * 获取或惰性初始化用户的隐私配置
     */
    public function getPrivacySetting(string $key, string $default = 'public'): string
    {
        // 若关联未加载且记录不存在，走默认配置
        if (!$this->relationLoaded('privacySetting') && !$this->privacySetting()->exists()) {
            return $default;
        }

        return $this->privacySetting?->getSetting($key, $default) ?? $default;
    }


}
