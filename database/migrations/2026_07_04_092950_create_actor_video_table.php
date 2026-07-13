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
        Schema::create('actor_video', function (Blueprint $table) {
            // foreignId 默认创建 unsignedBigInteger 字段，constrained 会自动关联对应的表，cascadeOnDelete 实现级联删除
            $table->foreignId('actor_id')->constrained('actors');
            $table->foreignId('video_id')->constrained('videos');

            $table->timestamp('created_at')->nullable();

            // 定义复合主键，确保一个演员在同一部视频中只有一条主记录
            $table->primary(['actor_id', 'video_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('actor_video');
    }
};
