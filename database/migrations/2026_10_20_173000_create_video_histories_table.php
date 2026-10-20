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
        Schema::create('video_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained('videos')->cascadeOnDelete()->comment('关联视频 ID');
            $table->foreignId('correction_id')->nullable()->constrained('video_corrections')->nullOnDelete()->comment('关联修正申请 ID (若由审核触发)');
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete()->comment('操作人/审核管理员 ID');
            $table->string('action_type', 50)->comment('修改行为标识, 如 update_name_zh, update_actors, update_description 等');
            $table->jsonb('old_values')->nullable()->comment('修改前原始数据快照');
            $table->jsonb('new_values')->nullable()->comment('修改后生效数据快照');
            $table->text('remark')->nullable()->comment('变更说明/审核批注');
            $table->timestamps();

            $table->index(['video_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_histories');
    }
};

