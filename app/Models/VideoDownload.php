<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Scout\Searchable;

/**
 * @property int $id
 * @property int $video_id 关联视频ID
 * @property int|null $user_id 上传/贡献用户ID
 * @property string $title 资源标题/文件名
 * @property string $type 下载类型: magnet, torrent, netdisk, ed2k, direct, store
 * @property string $cost_type 费用类型: free, paid
 * @property string|null $resolution 清晰度: 4K, 1080P, 720P
 * @property string|null $price 价格/标价
 * @property string $link 下载链接/磁力/网盘地址
 * @property string|null $hash info_hash 或特征哈希
 * @property int|null $file_size 文件大小(字节)
 * @property string|null $extraction_code 提取码
 * @property string|null $archive_password 解压密码
 * @property string|null $description 说明/备注
 * @property int $status 状态: 1-有效, 0-禁用
 * @property int $sort_order 排序权重
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Video $video
 * @property-read \App\Models\User|null $user
 */
class VideoDownload extends Model
{
    use Searchable;

    protected $fillable = [
        'video_id',
        'user_id',
        'title',
        'type',
        'cost_type',
        'resolution',
        'price',
        'link',
        'hash',
        'file_size',
        'extraction_code',
        'archive_password',
        'description',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'video_id' => 'integer',
        'user_id' => 'integer',
        'file_size' => 'integer',
        'status' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * 关联视频模型
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    /**
     * 关联贡献/上传用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 本地作用域：仅有效/上架的下载项
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    /**
     * 配置 Meilisearch / Scout 索引数组
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (int) $this->id,
            'video_id' => (int) $this->video_id,
            'title' => (string) $this->title,
            'type' => (string) $this->type,
            'resolution' => (string) ($this->resolution ?? ''),
            'hash' => (string) ($this->hash ?? ''),
            'description' => (string) ($this->description ?? ''),
            'status' => (int) $this->status,
        ];
    }
}

