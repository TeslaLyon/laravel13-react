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
        Schema::create('article_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')
                ->primary()
                ->constrained('articles')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            // 正文大文本字段 (LONGTEXT)
            $table->longText('content')->comment('文章正文内容 (HTML 或 Markdown)');
            $table->string('content_format', 20)->default('html')->comment('内容格式: html, markdown, rich-text');

            // SEO 优化扩展字段
            $table->string('seo_title')->nullable()->comment('SEO 标题');
            $table->string('seo_description', 500)->nullable()->comment('SEO 描述');
            $table->string('seo_keywords')->nullable()->comment('SEO 关键词');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_details');
    }
};
