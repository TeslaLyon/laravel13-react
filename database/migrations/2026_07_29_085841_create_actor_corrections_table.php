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
        Schema::create('actor_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('actors')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null'); // 提交修正的用户

            // 使用 JSONB 存储用户提交的完整修正数据（包括自定义的 key-value）
            $table->jsonb('payload')->comment('修正的内容 JSON');
            $table->unsignedTinyInteger('status')
                ->default(1)
                ->index()
                ->comment('审核状态: 1-待审核(pending), 2-已通过(approved), 3-已拒绝(rejected)');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('actor_corrections');
    }
};
