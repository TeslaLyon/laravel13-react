<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UserAvatarDecoration extends Pivot
{
    /**
     * 关联的数据表名
     */
    protected $table = 'user_avatar_decorations';

    /**
     * 自动将中间表的时间字段转换为 Carbon 实例
     */
    protected $casts = [
        'unlocked_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
