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
        Schema::create('user_privacy_settings', function (Blueprint $table) {
            $table->id();
            // 绑定用户 ID，建立外键约束与唯一索引（1 对 1 关系）
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            // PostgreSQL 18 原生 JSONB 存储，预设全站默认访问级别
            // 可选值标准：public(公开), followers(仅粉丝), private(仅自己)
            $table->jsonb('settings')->default(json_encode([
                'video_favorites' => 'public',
                'actor_favorites' => 'public',
                'liked_videos' => 'followers',
            ]))->comment('各项模块隐私权限字典');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_privacy_settings');
    }
};
