<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        $categories = [
            [
                'name' => 'Asian',
                'name_zh' => '亚洲',
                'slug' => 'asian',
                'description' => '包含各类亚洲风格的视频内容。',
                'avatar' => '/storage/avatars/asian_square.jpg',
                'avatar_vertical' => '/storage/avatars/asian_vertical.jpg',
                'avatar_horizontal' => '/storage/avatars/asian_horizontal.jpg',
                'video_num' => rand(100, 1000),
                'follow_num' => rand(500, 5000),
                'photo_num' => rand(50, 500),
                'sort' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Western',
                'name_zh' => '欧美',
                'slug' => 'western',
                'description' => '精选欧美风格视频。',
                'avatar' => '/storage/avatars/western_square.jpg',
                'avatar_vertical' => '/storage/avatars/western_vertical.jpg',
                'avatar_horizontal' => '/storage/avatars/western_horizontal.jpg',
                'video_num' => rand(100, 1000),
                'follow_num' => rand(500, 5000),
                'photo_num' => rand(50, 500),
                'sort' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Amateur',
                'name_zh' => '素人',
                'slug' => 'amateur',
                'description' => '真实分享，素人自拍内容。',
                'avatar' => '/storage/avatars/amateur_square.jpg',
                'avatar_vertical' => '/storage/avatars/amateur_vertical.jpg',
                'avatar_horizontal' => '/storage/avatars/amateur_horizontal.jpg',
                'video_num' => rand(100, 1000),
                'follow_num' => rand(500, 5000),
                'photo_num' => rand(50, 500),
                'sort' => 30,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Anime',
                'name_zh' => '动漫',
                'slug' => 'anime',
                'description' => '二次元、3D动画及同人作品。',
                'avatar' => '/storage/avatars/anime_square.jpg',
                'avatar_vertical' => '/storage/avatars/anime_vertical.jpg',
                'avatar_horizontal' => '/storage/avatars/anime_horizontal.jpg',
                'video_num' => rand(100, 1000),
                'follow_num' => rand(500, 5000),
                'photo_num' => rand(50, 500),
                'sort' => 40,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'VR',
                'name_zh' => '虚拟现实',
                'slug' => 'vr',
                'description' => '沉浸式全景VR体验视频。',
                'avatar' => '/storage/avatars/vr_square.jpg',
                'avatar_vertical' => '/storage/avatars/vr_vertical.jpg',
                'avatar_horizontal' => '/storage/avatars/vr_horizontal.jpg',
                'video_num' => rand(100, 1000),
                'follow_num' => rand(500, 5000),
                'photo_num' => rand(50, 500),
                'sort' => 50,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Cosplay',
                'name_zh' => '角色扮演',
                'slug' => 'cosplay',
                'description' => '经典角色服装扮演与剧情。',
                'avatar' => '/storage/avatars/cosplay_square.jpg',
                'avatar_vertical' => '/storage/avatars/cosplay_vertical.jpg',
                'avatar_horizontal' => '/storage/avatars/cosplay_horizontal.jpg',
                'video_num' => rand(100, 1000),
                'follow_num' => rand(500, 5000),
                'photo_num' => rand(50, 500),
                'sort' => 60,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];



        $rows = array_map(function ($item) use ($now) {
            return [
                'name' => $item['name'],
                'name_zh' => $item['name_zh'],
                'slug' => Str::slug($item['name']),
                'description' => $item['description'],
                'video_num' => $item['video_num'],
                'follow_num' => $item['follow_num'],
                'photo_num' => $item['photo_num'],
                'sort' => $item['sort'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $categories);

        DB::table('categories')->upsert(
            $rows,
            ['slug'],
            ['name', 'name_zh', 'updated_at']
        );
    }
}
