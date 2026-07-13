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
        Schema::create('video_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('video_id')->unique()->comment('视频id');
            $table->text('video_urls')->nullable()->comment('详情页视频预览');
            $table->text('screen_img')->nullable()->comment('视频片段截图');
            $table->text('list_img_large_meta')->nullable()->comment('详情页头图');
            $table->text('download_info')->nullable()->comment('下载信息');
            $table->string('movie_length')->default('')->comment('视频长度，单位：秒');
            $table->text('description')->nullable()->comment('描述');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_details');
    }
};
