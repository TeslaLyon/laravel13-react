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
        Schema::create('nodes', function (Blueprint $table) {
            $table->id();
            // 🎯 父级节点 ID (0 或 null 代表顶级节点)
            $table->foreignId('parent_id')->nullable()->constrained('nodes')->nullOnDelete();

            // 🎯 节点类型 (XenForo 核心机制): category, forum, link, page
            $table->string('node_type', 25)->default('forum');

            $table->string('title');                          // 节点名称
            $table->string('slug')->unique();                // URL 标识
            $table->text('description')->nullable();         // 节点描述
            $table->string('icon')->nullable();              // 图标
            $table->unsignedInteger('display_order')->default(1); // 排序权重 (对应 display_order)

            $table->boolean('is_active')->default(true);     // 是否启用

            $table->timestamps();
            $table->softDeletes();

            // 索引优化
            $table->index(['parent_id', 'display_order']);
            $table->index('node_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nodes');
    }
};
