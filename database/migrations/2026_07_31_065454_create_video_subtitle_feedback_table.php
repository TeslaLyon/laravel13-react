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
        Schema::create('video_subtitle_feedback', function (Blueprint $table) {
            $table->id();

            // 关联的字幕 ID，如果字幕被删，反馈级联删除
            $table->foreignId('video_subtitle_id')->constrained()->cascadeOnDelete();

            // 提交反馈的用户 ID。假设必须登录才能反馈，如果用户被删，这里设为 NULL 保留反馈记录
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // 反馈的具体内容
            $table->text('content');

            // 🌟 反馈处理状态：1-待处理(pending), 2-已解决(resolved), 3-已忽略(ignored)
            $table->unsignedTinyInteger('status')
                ->default(1)
                ->index()
                ->comment('处理状态: 1-待处理(pending), 2-已解决(resolved), 3-已忽略(ignored)');

            // 管理员的处理备注（可选）
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_subtitle_feedback');
    }
};
