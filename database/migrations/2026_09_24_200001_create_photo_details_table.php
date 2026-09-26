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
        Schema::create('photo_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('photo_id')->unique()->constrained('photos')->cascadeOnDelete()->comment('关联写真主表');
            $table->text('gallery')->nullable()->comment('整套写真高清图片列表（JSON 结构存储）');
            $table->text('download_info')->nullable()->comment('高清原图打包下载信息/网盘链接');
            $table->text('description')->nullable()->comment('写真套图介绍描述');
            $table->jsonb('extra_meta')->default('{}')->comment('拍摄设备、分辨率等扩展参数');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('photo_details');
    }
};

