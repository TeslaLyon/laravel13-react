<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Article;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Article>
 */
class ArticleFactory extends Factory
{
    protected $model = Article::class;

    /**
     * 定义文章主表属性数据
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->realText(rand(15, 30));

        return [
            'user_id' => User::inRandomOrder()->value('id') ?? User::factory(),
            'title' => $title,
            'slug' => Str::slug(fake()->unique()->words(3, true)) . '-' . rand(1000, 9999),
            'excerpt' => fake()->realText(rand(80, 150)),
            'cover_image' => 'https://images.unsplash.com/photo-' . fake()->numberBetween(1500000000000, 1600000000000) . '?w=800&auto=format&fit=crop&q=80',
            'read_time' => rand(3, 15) . ' 分钟阅读',
            'views_count' => rand(100, 10000),
            'status' => 1,
            'published_at' => fake()->dateTimeBetween('-6 months', 'now'),
        ];
    }

    /**
     * 工厂后置钩子：创建完主表后绑定关联表
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Article $article) {
            // 1. 生成 1:1 的 ArticleDetail 详情正文数据
            $paragraphs = fake()->paragraphs(rand(4, 8));
            $formattedContent = '<p class="mb-4 text-base leading-relaxed">'
                . implode('</p><p class="mb-4 text-base leading-relaxed">', $paragraphs)
                . '</p>';

            $article->detail()->create([
                'content' => $formattedContent,
                'content_format' => 'html',
                'seo_title' => $article->title,
                'seo_description' => $article->excerpt,
                'seo_keywords' => implode(',', fake()->words(5)),
            ]);

            // 2. 绑定分类：必须使用复数 categories()，它对应 BelongsToMany 关系 (Line 62)
            $categoryIds = fake()->randomElements(range(1, 6), rand(1, 3));
            $article->categories()->attach($categoryIds);

            // 3. 绑定标签：必须使用复数 tags()，它对应 BelongsToMany 关系 (Line 63)
            $tagIds = fake()->randomElements(range(1, 100), rand(2, 5));
            $article->tags()->attach($tagIds);
        });
    }
}
