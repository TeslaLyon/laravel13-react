<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('写真/套图原始标题');
            $table->string('name_zh')->default('')->comment('写真中文标题');
            $table->string('slug')->comment('写真URL友好标识');
            $table->string('source_uuid')->unique()->nullable()->comment('远端唯一标识');
            $table->text('cover_img')->nullable()->comment('封面海报图（支持 WebP 与多分辨率结构 JSON）');
            $table->unsignedInteger('total')->default(0)->comment('该套图包含的照片总张数');
            $table->timestamp('release_at')->nullable()->comment('发布时间');
            $table->string('photo_code')->nullable()->comment('写真套图统一番号/编码');
            $table->foreignId('channel_id')->nullable()->constrained('channels')->nullOnDelete()->comment('所属片商');
            $table->unsignedTinyInteger('sexual_orientation')->default(0)->comment('性取向：[0-未知; 1-异性; 2-女同性恋]');
            $table->boolean('is_trans_model')->default(false)->comment('模特是否为变性人');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态：[0-下架; 1-上架]');
            $table->unsignedInteger('view_num')->default(0)->comment('浏览次数');
            $table->unsignedInteger('likes_count')->default(0)->comment('点赞数');
            $table->unsignedInteger('favorites_count')->default(0)->comment('收藏数');
            $table->unsignedBigInteger('love_reactant_id')->nullable()->comment('Laravel Love 交互实体 ID');
            $table->softDeletes();
            $table->timestamps();

            // 复合索引与性能查询优化
            $table->index('slug');
            $table->index('photo_code');
            $table->index(['status', 'deleted_at'], 'idx_photo_status_deleted');
            $table->index(['channel_id', 'deleted_at'], 'idx_photo_channel_deleted');
            $table->index(['created_at', 'deleted_at'], 'idx_photo_created_deleted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};

