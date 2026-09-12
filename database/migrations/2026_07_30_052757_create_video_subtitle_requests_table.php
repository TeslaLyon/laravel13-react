<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('video_subtitle_requests', function (Blueprint $table) {
            $table->id();
            // 申请对应哪个视频
            $table->foreignId('video_id')->constrained()->onDelete('cascade');

            // 是哪个用户发起的申请
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // 🌟 申请状态：1-求字幕中(pending), 2-愿望达成(fulfilled), 3-已取消/关闭(closed)
            $table->unsignedTinyInteger('status')
                ->default(1)
                ->index()
                ->comment('申请状态: 1-求字幕中(pending), 2-愿望达成(fulfilled), 3-已关闭(closed)');

            // 确保同一个用户对同一个视频只能发起一次申请
            $table->unique(['video_id', 'user_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_subtitle_requests');
    }
};
