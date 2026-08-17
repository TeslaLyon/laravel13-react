<?php

namespace Database\Seeders;

use App\Models\Article;
use Illuminate\Database\Seeder;

class ArticleSeeder extends Seeder
{
    /**
     * 执行数据库填充
     */
    public function run(): void
    {
        // 批量创建 30 篇文章及关联数据
        Article::factory()->count(30)->create();
    }
}
