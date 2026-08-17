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
        Schema::create('article_tag', function (Blueprint $table) {
            // 外键 1：关联 articles 表
            $table->foreignId('article_id')
                ->constrained('articles')
                ->cascadeOnDelete();

            // 外键 2：关联 tags 表
            $table->foreignId('tag_id')
                ->constrained('tags')
                ->cascadeOnDelete();

            // 设置复合主键：防止重复关联，并提高多对多查询性能
            $table->primary(['article_id', 'tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_tag');
    }
};
