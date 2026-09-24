<?php

namespace Database\Seeders;

use App\Models\Channel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ChannelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 按照预期排序定义片商数据（不显式指定 id，由数据库主键序列自动自增生成）
        $channels = [
            [
                'name'                 => 'Brazzers',
                'slug'                 => 'brazzers',
                'data_crawl_type'      => 1,
                'video_num'            => 0,
                'follow_num'           => 0,
                'avatar'               => '/images/channels/brazzers-avatar.avif',
                'logo'                 => '/images/channels/brazzers-logo.avif',
                'official_website_url' => 'https://brazzers.com/',
                'created_at'           => '2024-09-06 10:58:11',
                'updated_at'           => '2026-07-01 12:47:19',
                'deleted_at'           => null,
                'love_reactant_id'     => 1,
            ],
            [
                'name'                 => 'Vixen',
                'slug'                 => 'vixen',
                'data_crawl_type'      => 2,
                'video_num'            => 510,
                'follow_num'           => 0,
                'avatar'               => '/images/channels/vixen-avatar.avif',
                'logo'                 => '/images/channels/vixen-logo.avif',
                'official_website_url' => 'https://www.vixen.com',
                'created_at'           => '2024-09-11 11:40:43',
                'updated_at'           => '2026-07-01 12:47:19',
                'deleted_at'           => null,
                'love_reactant_id'     => 2,
            ],
            [
                'name'                 => 'Blacked',
                'slug'                 => 'blacked',
                'data_crawl_type'      => 2,
                'video_num'            => 667,
                'follow_num'           => 0,
                'avatar'               => '/images/channels/blacked-avatar.avif',
                'logo'                 => '/images/channels/blacked-logo.avif',
                'official_website_url' => 'https://www.blacked.com',
                'created_at'           => '2024-09-11 20:26:22',
                'updated_at'           => '2026-07-01 12:47:19',
                'deleted_at'           => null,
                'love_reactant_id'     => 3,
            ],
            [
                'name'                 => 'Tushy',
                'slug'                 => 'tushy',
                'data_crawl_type'      => 2,
                'video_num'            => 578,
                'follow_num'           => 0,
                'avatar'               => '/images/channels/tushy-avatar.avif',
                'logo'                 => '/images/channels/tushy-logo.avif',
                'official_website_url' => 'https://www.tushy.com',
                'created_at'           => '2024-09-11 20:54:20',
                'updated_at'           => '2026-07-01 12:47:19',
                'deleted_at'           => null,
                'love_reactant_id'     => 4,
            ],
            [
                'name'                 => 'Deeper',
                'slug'                 => 'deeper',
                'data_crawl_type'      => 2,
                'video_num'            => 367,
                'follow_num'           => 0,
                'avatar'               => '/images/channels/deeper.avif',
                'logo'                 => '/images/channels/deeper.avif',
                'official_website_url' => 'https://www.deeper.com',
                'created_at'           => '2024-09-11 21:14:02',
                'updated_at'           => '2026-07-01 12:47:19',
                'deleted_at'           => null,
                'love_reactant_id'     => 5,
            ],
            [
                'name'                 => 'Milfy',
                'slug'                 => 'milfy',
                'data_crawl_type'      => 2,
                'video_num'            => 72,
                'follow_num'           => 0,
                'avatar'               => '/images/channels/milfy-avatar.avif',
                'logo'                 => '/images/channels/milfy-logo.avif',
                'official_website_url' => 'https://www.milfy.com',
                'created_at'           => '2024-09-11 21:16:53',
                'updated_at'           => '2026-07-01 12:47:19',
                'deleted_at'           => null,
                'love_reactant_id'     => 6,
            ],
            [
                'name'                 => 'Slayed',
                'slug'                 => 'slayed',
                'data_crawl_type'      => 2,
                'video_num'            => 139,
                'follow_num'           => 0,
                'avatar'               => '/images/channels/slayed-avatar.avif',
                'logo'                 => '/images/channels/slayed-logo.avif',
                'official_website_url' => 'https://www.slayed.com',
                'created_at'           => '2024-09-11 21:18:02',
                'updated_at'           => '2026-07-01 12:47:19',
                'deleted_at'           => null,
                'love_reactant_id'     => 7,
            ],
        ];

        // 1. 若存在 love_reactants 表，预先保障 reactant 外键存在
        if (Schema::hasTable('love_reactants')) {
            $channelMorph = (new Channel())->getMorphClass();
            foreach ($channels as $channel) {
                if (!empty($channel['love_reactant_id'])) {
                    DB::table('love_reactants')->updateOrInsert(
                        ['id' => $channel['love_reactant_id']],
                        [
                            'type'       => $channelMorph,
                            'created_at' => $channel['created_at'],
                            'updated_at' => $channel['updated_at'],
                        ]
                    );
                }
            }
        }

        // 2. 以 slug 为唯一标识进行幂等写入：已存在则更新，不存在则插入（id 自动自增）
        foreach ($channels as $channelData) {
            $existing = DB::table('channels')->where('slug', $channelData['slug'])->first();

            if ($existing) {
                // 已存在时，仅精准同步更新 avatar 与 logo，其他所有线上数据与外键绝不受影响
                DB::table('channels')->where('slug', $channelData['slug'])->update([
                    'avatar'     => $channelData['avatar'] ?? $existing->avatar,
                    'logo'       => $channelData['logo'] ?? $existing->logo,
                    'updated_at' => now()->toDateTimeString(),
                ]);
            } else {
                // 不存在时纯自增插入（不携带 id 字段，由数据库序列自然递增生成）
                DB::table('channels')->insert($channelData);
            }
        }
    }
}
