<?php

namespace Database\Seeders;

use App\Enums\DeliveryType;
use App\Enums\PreviewType;
use App\Enums\ProductType;
use App\Models\Actor;
use App\Models\Product;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class StoreSeeder extends Seeder
{
    public function run(): void
    {
        // 1. 获取现有 Tag ID (优先使用 1~400 范围，避免外键不存在报错)
        $availableTagIds = Tag::whereBetween('id', [1, 400])->pluck('id')->toArray();
        if (empty($availableTagIds)) {
            $availableTagIds = Tag::pluck('id')->toArray();
        }

        // 2. 获取现有的 Actor 1, 2
        $availableActorIds = Actor::whereIn('id', [1, 2])->pluck('id')->toArray();
        if (empty($availableActorIds)) {
            $availableActorIds = Actor::limit(2)->pluck('id')->toArray();
        }

        // 3. 商品与详情数据定义
        $productsData = [
            [
                'title' => 'DaVinci Resolve 19 调色大师班 - 从入门到电影级色彩科学全解析 (2026版)',
                'slug' => 'davinci-resolve-19-masterclass-2026',
                'type' => ProductType::VIDEO,
                'list_img' => 'https://images.unsplash.com/photo-1574717024653-61fd2cf4d44d?q=80&w=1920&auto=format&fit=crop',
                'preview_type' => PreviewType::VIDEO,
                'preview_data' => ['video_url' => 'https://www.w3schools.com/html/mov_bbb.mp4'],
                'duration_seconds' => 45900, // 12:45:00
                'resolution' => '4k',
                'spec_badge' => ['label' => '附赠工程', 'color' => 'indigo'],
                'price' => 199.00,
                'original_price' => 299.00,
                'views_count' => 34200,
                'sales_count' => 856,
                'detail' => [
                    'specs' => ['软件版本' => 'DaVinci Resolve 19+', '总课时' => '48节', '工程大小' => '12.8GB'],
                    'content' => '## 课程大纲\n1. 色彩科学与 ACES 工作流\n2. 节点架构与胶片模拟\n3. 商业广告实战调色。',
                    'delivery_type' => DeliveryType::NETDISK,
                    'delivery_content' => [
                        'pan_url' => 'https://pan.baidu.com/s/1demo_davinci_resolve',
                        'pan_code' => '8k88',
                        'unzip_password' => 'davinci_2026_vip',
                    ],
                ],
            ],
            [
                'title' => '赛博朋克 2077 风格夜景建筑摄影素材包 (包含 50 张精修原图)',
                'slug' => 'cyberpunk-night-architecture-raw-50',
                'type' => ProductType::PHOTO,
                'list_img' => 'https://images.unsplash.com/photo-1605810230434-7631ac76ec81?q=80&w=1920&auto=format&fit=crop',
                'preview_type' => PreviewType::CAROUSEL,
                'preview_data' => [
                    'images' => [
                        'https://images.unsplash.com/photo-1605810230434-7631ac76ec81?q=80&w=1920',
                        'https://images.unsplash.com/photo-1519501025264-65ba15a82390?q=80&w=1920',
                    ],
                ],
                'duration_seconds' => 0,
                'resolution' => null,
                'spec_badge' => ['label' => '8K RAW', 'color' => 'purple'],
                'price' => 39.90,
                'original_price' => null,
                'views_count' => 12400,
                'sales_count' => 342,
                'detail' => [
                    'specs' => ['数量' => '50张 DNG 原片', '单张分辨率' => '8192 x 5464', '色彩空间' => 'Adobe RGB'],
                    'content' => '重庆、东京两地实拍夜景赛博朋克风格建筑原片，适合作为合成背景与概念设计素材。',
                    'delivery_type' => DeliveryType::NETDISK,
                    'delivery_content' => [
                        'pan_url' => 'https://pan.quark.cn/s/demo_cyberpunk_raw',
                        'pan_code' => 'raw9',
                        'unzip_password' => 'neon_2077_free',
                    ],
                ],
            ],
            [
                'title' => 'Blender 4.0 几何节点生成科幻城市完整工程文件',
                'slug' => 'blender-4-geo-nodes-sci-fi-city',
                'type' => ProductType::PHOTO,
                'list_img' => 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?q=80&w=1920&auto=format&fit=crop',
                'preview_type' => PreviewType::GIF,
                'preview_data' => ['gif_url' => 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?q=80&w=800'],
                'duration_seconds' => 11720, // 03:15:20
                'resolution' => '1080p',
                'spec_badge' => ['label' => 'Blender 4.0', 'color' => 'indigo'],
                'price' => 89.00,
                'original_price' => null,
                'views_count' => 8900,
                'sales_count' => 210,
                'detail' => [
                    'specs' => ['渲染引擎' => 'Cycles / Eevee', '面数' => '程序化动态控制', '贴图格式' => '4K PBR'],
                    'content' => '全流程几何节点科幻城市资产包，支持参数化修改建筑高度、霓虹发光与车流密度。',
                    'delivery_type' => DeliveryType::NETDISK,
                    'delivery_content' => [
                        'pan_url' => 'https://pan.baidu.com/s/demo_blender_city',
                        'pan_code' => 'cg88',
                        'unzip_password' => 'blender_city_vip',
                    ],
                ],
            ],
            [
                'title' => '极简主义 UI 设计情绪板素材合集 (全面支持 Figma & Sketch)',
                'slug' => 'minimalist-ui-moodboard-design-kit',
                'type' => ProductType::PHOTO,
                'list_img' => 'https://images.unsplash.com/photo-1561070791-2526d30994b5?q=80&w=1920&auto=format&fit=crop',
                'preview_type' => PreviewType::IMAGE,
                'preview_data' => null,
                'duration_seconds' => 0,
                'resolution' => null,
                'spec_badge' => ['label' => 'Vector/PNG', 'color' => 'rose'],
                'price' => 9.99,
                'original_price' => 29.99,
                'views_count' => 45100,
                'sales_count' => 1560,
                'detail' => [
                    'specs' => ['支持软件' => 'Figma, Sketch, Adobe XD', '组件数量' => '120+ 模块', '文件格式' => '.fig, .sketch'],
                    'content' => '包含现代排版、卡片栅格与配色规范的情绪板 UI Kit，支持快速搭建产品展示页。',
                    'delivery_type' => DeliveryType::CARD_KEY,
                    'delivery_content' => [
                        'license_key' => 'FIGMA-PRO-MOODBOARD-2026-ABCD',
                        'figma_link' => 'https://www.figma.com/community/file/demo_moodboard_kit',
                    ],
                ],
            ],
            [
                'title' => 'Sony A7M4 S-Log3 电影感 LUTs 预设包 (附套用与微调教程)',
                'slug' => 'sony-a7m4-slog3-cinematic-luts-pack',
                'type' => ProductType::VIDEO,
                'list_img' => 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?q=80&w=1920&auto=format&fit=crop',
                'preview_type' => PreviewType::VIDEO,
                'preview_data' => ['video_url' => 'https://www.w3schools.com/html/mov_bbb.mp4'],
                'duration_seconds' => 1540, // 00:25:40
                'resolution' => '4k',
                'spec_badge' => ['label' => '15款 .CUBE', 'color' => 'emerald'],
                'price' => 59.00,
                'original_price' => 99.00,
                'views_count' => 21300,
                'sales_count' => 789,
                'detail' => [
                    'specs' => ['LUT格式' => '.cube (33x33x33)', '适用机型' => 'A7M4, FX3, FX30, A7S3', '伽马曲线' => 'S-Log3 / S-Gamut3.Cine'],
                    'content' => '精准还原 Kodak 2383 胶片肤色，内附 25 分钟 S-Log3 曝光与降噪微调实战视频。',
                    'delivery_type' => DeliveryType::NETDISK,
                    'delivery_content' => [
                        'pan_url' => 'https://pan.baidu.com/s/demo_slog3_luts',
                        'pan_code' => 'lut7',
                        'unzip_password' => 'sony_cine_luts',
                    ],
                ],
            ],
            [
                'title' => '冰川与极光 - 冰岛无人机航拍精选图集 (大画幅输出级)',
                'slug' => 'iceland-glacier-aurora-drone-gallery',
                'type' => ProductType::PHOTO,
                'list_img' => 'https://images.unsplash.com/photo-1476610182048-b716b8518aae?q=80&w=1920&auto=format&fit=crop',
                'preview_type' => PreviewType::IMAGE,
                'preview_data' => null,
                'duration_seconds' => 0,
                'resolution' => null,
                'spec_badge' => ['label' => '50MP 输出', 'color' => 'cyan'],
                'price' => 129.00,
                'original_price' => null,
                'views_count' => 6700,
                'sales_count' => 145,
                'detail' => [
                    'specs' => ['画幅分辨率' => '8688 x 5792', '包含数量' => '30幅大作', '色彩配置文件' => 'ProPhoto RGB'],
                    'content' => '哈苏中画幅无人机机载相机拍摄，完美保留冰川蓝冰与极光微弱暗部细节。',
                    'delivery_type' => DeliveryType::NETDISK,
                    'delivery_content' => [
                        'pan_url' => 'https://pan.quark.cn/s/demo_iceland_gallery',
                        'pan_code' => 'ice8',
                        'unzip_password' => 'iceland_aurora_2026',
                    ],
                ],
            ],
        ];

        // 4. 持久化商品并绑定已有 Tag (1~400 随机) 与 Actor (1 或 2)
        foreach ($productsData as $item) {
            DB::transaction(function () use ($item, $availableTagIds, $availableActorIds) {
                // 创建或更新商品主记录
                $product = Product::updateOrCreate(
                    ['slug' => $item['slug']],
                    [
                        'title' => $item['title'],
                        'type' => $item['type'],
                        'list_img' => $item['list_img'],
                        'preview_type' => $item['preview_type'],
                        'preview_data' => $item['preview_data'],
                        'duration_seconds' => $item['duration_seconds'],
                        'resolution' => $item['resolution'],
                        'spec_badge' => $item['spec_badge'],
                        'price' => $item['price'],
                        'original_price' => $item['original_price'],
                        'views_count' => $item['views_count'],
                        'sales_count' => $item['sales_count'],
                        'status' => 1,
                        'sort_order' => 0,
                    ]
                );

                // 创建或更新商品详情
                $product->detail()->updateOrCreate(
                    ['product_id' => $product->id],
                    [
                        'specs' => $item['detail']['specs'],
                        'content' => $item['detail']['content'],
                        'delivery_type' => $item['detail']['delivery_type'],
                        'delivery_content' => $item['detail']['delivery_content'],
                    ]
                );

                // 随机从现有 Tag 池 (1~400) 抽取 2~4 个 Tag 绑定
                if (!empty($availableTagIds)) {
                    $randomCount = min(rand(2, 4), count($availableTagIds));
                    $selectedTagIds = (array) Arr::random($availableTagIds, $randomCount);
                    $product->tags()->sync($selectedTagIds);
                }

                // 随机选择 Actor 1 或 2 绑定
                if (!empty($availableActorIds)) {
                    $selectedActorId = Arr::random($availableActorIds);
                    $product->actors()->sync([$selectedActorId]);
                }
            });
        }
    }
}
