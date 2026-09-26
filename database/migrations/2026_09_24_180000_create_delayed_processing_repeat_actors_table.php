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
        Schema::create('delayed_processing_repeat_actors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->nullable()->constrained('channels')->nullOnDelete();
            $table->string('video_slug')->comment('视频唯一标识，通常为 source_uuid 或 slug');
            $table->string('actor_slug')->nullable()->comment('触发冲突判定的演员 slug');
            $table->unsignedTinyInteger('type')->default(0)->comment('冲突类型：[0-延期观察队列; 1-同名不同人; 2-库中存在多个同名演员记录]');
            $table->unsignedTinyInteger('status')->default(0)->comment('处理状态：[0-待人工处理; 1-已手动消解; 2-直接忽略]');
            $table->text('note')->nullable()->comment('人工处理备注');
            $table->timestamps();

            $table->index(['video_slug', 'type'], 'idx_repeat_video_type');
            $table->index('actor_slug');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delayed_processing_repeat_actors');
    }
};

