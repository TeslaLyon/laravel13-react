<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // 1. 基础信息
            $table->string('title', 255)->comment('商品主标题');
            $table->string('slug', 120)->nullable()->unique()->comment('SEO友好的唯一别名');
            $table->string('type', 30)->default('video')->comment('商品类型: video, source_code, ebook, asset');
            $table->string('list_img', 500)->comment('列表主封面图');

            // 2. 列表悬停与轻量预览配置
            $table->string('preview_type', 30)->default('image')->comment('列表预览形态: video, carousel, gif, image');
            $table->json('preview_data')->nullable()->comment('预览媒体配置(试看切片url/GIF动图/多图画廊)');
            $table->unsignedInteger('duration_seconds')->default(0)->comment('视频总时长(秒)');

            // 3. 特殊徽标与规格
            $table->string('resolution', 20)->nullable()->comment('视频分辨率: 8k, 4k, 1080p, 720p');
            $table->json('spec_badge')->nullable()->comment('自定义规格徽标: {"label": "...", "color": "..."}');

            // 4. 定价体系 (单位: 分)
            $table->bigInteger('price')->comment('售价(分)');
            $table->bigInteger('original_price')->nullable()->comment('划线原价(分)');

            // 5. 流量与统计数据 (纯整数)
            $table->unsignedBigInteger('views_count')->default(0)->comment('真实浏览量');
            $table->unsignedInteger('sales_count')->default(0)->comment('销量');

            // 6. 状态与权重
            $table->unsignedTinyInteger('status')->default(1)->comment('状态: 1-在售, 2-下架, 3-草稿');
            $table->integer('sort_order')->default(0)->comment('排序权重(越大越靠前)');

            $table->timestamps();

            // ==================== 索引优化设计 ====================

            // 场景 1: 商城默认列表排序 (上架状态 + 自定义权重 + 创建时间)
            $table->index(['status', 'sort_order', 'created_at'], 'idx_status_sort_created');

            // 场景 2: 商品类型/分类筛选列表 (上架状态 + 类型 + 创建时间)
            $table->index(['status', 'type', 'created_at'], 'idx_status_type_created');

            // 场景 3: 视频分辨率专区筛选 (如只看 4K / 8K 教程)
            $table->index(['status', 'resolution'], 'idx_status_resolution');

            // 场景 4: 热门榜单排序 (上架状态 + 按销量/热度倒序)
            $table->index(['status', 'sales_count'], 'idx_status_sales');
            $table->index(['status', 'views_count'], 'idx_status_views');

            // 场景 5: 价格区间筛选与排序 (上架状态 + 按价格高低)
            $table->index(['status', 'price'], 'idx_status_price');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
