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
        Schema::create('video_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('video_id')->constrained();
            // 修正类型: 'actor' (演员), 'category' (分类), 'tag' (标签)
            $table->string('type')->index();

            // 用于存储 JSON 数组字符串或纯文本
            $table->text('payload');

            // 🌟 审核状态：1-待审核(pending), 2-已通过(approved), 3-已拒绝(rejected)
            $table->unsignedTinyInteger('status')
                ->default(1)
                ->index()
                ->comment('审核状态: 1-待审核(pending), 2-已通过(approved), 3-已拒绝(rejected)');

            // 审核相关信息
            $table->foreignId('reviewer_id')->nullable()->constrained('users');
            $table->text('reject_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_corrections');
    }
};
