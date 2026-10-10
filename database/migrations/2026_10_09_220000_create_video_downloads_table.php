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
        Schema::create('video_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete()->comment('关联视频ID');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->comment('贡献/上传用户ID，爬虫或官方资源可为 null');

            $table->string('title')->comment('下载项标题/文件名，如 ABC-123.4K.mp4 或 官方原版');
            $table->string('type', 30)->default('magnet')->comment('下载类型: magnet(磁力), torrent(种子), netdisk(网盘), ed2k(电驴), direct(直链), store(官方商城)');
            $table->string('cost_type', 20)->default('free')->comment('费用类型: free(免费), paid(付费)');
            $table->string('resolution', 20)->nullable()->comment('清晰度: 4K, 1080P, 720P 等');
            $table->string('price', 50)->nullable()->comment('标价/付费信息');

            $table->text('link')->comment('下载地址/磁力链接/网盘URL');
            $table->string('hash', 64)->nullable()->index()->comment('磁力/种子 info_hash 或文件特征哈希，用于排重与全站快速检索');
            $table->unsignedBigInteger('file_size')->nullable()->comment('文件大小，单位：字节');

            $table->string('extraction_code', 50)->nullable()->comment('网盘提取码');
            $table->string('archive_password', 100)->nullable()->comment('文件解压密码');
            $table->text('description')->nullable()->comment('说明/备注信息');

            $table->unsignedTinyInteger('status')->default(1)->index()->comment('状态: 1-正常有效, 0-已下架/死链');
            $table->unsignedInteger('sort_order')->default(0)->comment('排序权重，越大越靠前');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_downloads');
    }
};

