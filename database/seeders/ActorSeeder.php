<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Actor;

class ActorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. 使用 updateOrCreate 安全地插入或更新主表 (actors) 数据
        // 参数一：查询条件；参数二：不存在时创建或存在时更新的数据
        $actor = Actor::updateOrCreate(
            ['slug' => 'angela-white'],
            [
                'name' => 'Angela White',
                // 可以使用真实的占位图或者网上的公开图片链接
                'follow_num' => 1560000,
                'view_num' => 9876500,
                'gender' => 2, // 2 代表女性
                'is_trans_model' => false,
            ]
        );

        // 2. 安全地插入或更新详情表 (actor_details) 数据
        // 同样使用 updateOrCreate，根据 actor_id 查找关联记录
        $actor->detail()->updateOrCreate(
            ['actor_id' => $actor->id],
            [
                'basic_info' => [
                    'name' => 'Angela White',
                    'aliases' => 'Ann',
                    'date_of_birth' => '1985-03-04',
                    'age' => '39',
                    'astrological_sign' => 'Pisces (双鱼座)',
                    'profession' => 'Actress, Director',
                    'nationality' => 'Australian (澳大利亚)',
                    'career_start' => '2003',
                ],

                'physical_info' => [
                    'ethnicity' => 'Caucasian (白人)',
                    'boobs' => 'Real',
                    'cup' => 'G',
                    'height' => '160 cm',
                    'hair_color' => 'Brown (棕发)',
                    'eye_color' => 'Hazel (榛色)',
                    'tattoos' => 'None (无)',
                    'piercings' => 'Ears (耳部)',
                ],

                'socials' => [
                    'x' => 'https://x.com/angelawhite',
                    'instagram' => 'https://instagram.com/theangelawhite',
                    'website' => 'https://angelawhite.com',
                    'youtube' => 'https://youtube.com/c/angelawhite'
                ]
            ]
        );
    }
}
