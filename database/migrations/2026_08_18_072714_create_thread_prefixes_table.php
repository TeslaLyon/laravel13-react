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
        Schema::create('thread_prefixes', function (Blueprint $table) {
            $table->id();
            $table->string('name');                         // 标签名称 (如: Verified, Request, OnlyFans)
            $table->string('slug')->unique();               // 唯一标识 (如: verified, request)
            $table->string('bg_color', 30)->default('#6f42c1'); // 背景色 Hex
            $table->string('text_color', 30)->default('#ffffff'); // 文字色 Hex
            $table->text('description')->nullable();        // 标签说明
            $table->unsignedInteger('display_order')->default(1); // 排序权重
            $table->boolean('is_active')->default(true);    // 是否启用

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'display_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thread_prefixes');
    }
};
