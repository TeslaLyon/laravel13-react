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
        Schema::create('article_category', function (Blueprint $table) {
            // 外键 1：关联 articles 表
            $table->foreignId('article_id')
                ->constrained('articles')
                ->cascadeOnDelete();

            // 外键 2：关联 categories 表
            $table->foreignId('category_id')
                ->constrained('categories')
                ->cascadeOnDelete();

            // 设置复合主键：防止重复关联并极大提升物理索引性能
            $table->primary(['article_id', 'category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_categories');
    }
};
