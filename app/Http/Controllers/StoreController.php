<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class StoreController extends Controller
{
    /**
     * 商城分类列表定义
     */
    protected const CATEGORIES = [
        ['label' => '全部', 'type' => null],
        ['label' => '视频教程', 'type' => 'video'],
        ['label' => '高清图库', 'type' => 'image'],
        ['label' => '素材工程', 'type' => 'source_code'],
        ['label' => '3D 模型', 'type' => 'asset'],
    ];

    public function index(Request $request): Response
    {
        $currentType = $request->query('type');

        return Inertia::render('store/index', [
            // 1. 首屏同步数据：面包屑与分类列表
            'breadcrumbs' => [
                ['title' => '首页', 'href' => route('home')],
                ['title' => '商城', 'href' => null],
            ],
            'categories' => self::CATEGORIES,
            'activeType' => $currentType,

            // 2. 核心商品流：通过 Inertia::defer 异步延迟加载，不阻塞首屏骨架呈现
            'products' => Inertia::defer(function () use ($currentType) {
                return Product::query()
                    ->onSale()
                    ->when($currentType, fn($query, $type) => $query->where('type', $type))
                    ->with([
                        'tags:id,name,name_zh',
                        'actors:id,name,avatar',
                    ])
                    ->defaultSort()
                    ->paginate(12)
                    ->withQueryString()
                    ->through(fn(Product $product) => [
                        'id' => $product->id,
                        'title' => $product->title,
                        'slug' => $product->slug,
                        'type' => $product->type->value,
                        'thumbnail' => $product->list_img,
                        'preview_type' => $product->preview_type->value,
                        'preview_data' => $product->preview_data,
                        'duration' => $product->duration_formatted,
                        'resolution' => $product->resolution ? strtoupper($product->resolution) : null,
                        'spec_badge' => $product->spec_badge,
                        'price' => $product->price,
                        'original_price' => $product->original_price,
                        'has_discount' => $product->has_discount,
                        'views_count' => $product->views_count,
                        'sales_count' => $product->sales_count,
                        'created_at_human' => $product->created_at->diffForHumans(),
                        // 主讲人/作者信息 (取首位 actor 或默认值)
                        'author' => $product->actors->first()?->name ?? '官方自营',
                        'avatar' => $product->actors->first()?->avatar,
                        // 标签列表
                        'tags' => $product->tags->map(fn($tag) => [
                            'id' => $tag->id,
                            'name' => $tag->name,
                            'name_zh' => $tag->name_zh,
                        ]),
                    ]);
            }),
        ]);
    }

    /**
     * 商品详情页 (支持 Route Model Binding 与 Inertia::defer)
     */
    public function show(Request $request, Product $product, string $slug): Response|SymfonyResponse
    {
        // 1. 安全检查：未上架商品抛出 404
        abort_if($product->status !== 1, 404);

        // 2. SEO 规范化：校验 URL Slug 一致性
        if ($product->slug && $product->slug !== $slug) {
            return redirect()->route('store.product.show', [
                'product' => $product->id,
                'slug' => $product->slug,
            ], 301);
        }

        return Inertia::render('store/show', [
            // 1. 首屏即时数据：面包屑
            'breadcrumbs' => [
                ['title' => '首页', 'href' => route('home')],
                ['title' => '商城', 'href' => route('store.index')],
                ['title' => $product->title, 'href' => null],
            ],

            // 2. 核心详情数据：Inertia::defer 异步延迟加载
            'product' => Inertia::defer(function () use ($request, $product) {
                // 修复：移除不存在的 title 和 bio 字段，仅查询已有字段
                $product->loadMissing([
                    'detail',
                    'tags:id,name,name_zh',
                    'actors:id,name,avatar',
                ]);

                // 浏览量自增
                $product->increment('views_count');

                // 校验已购权限
                $user = $request->user();
                $hasPurchased = false;

                if ($user) {
                    // $hasPurchased = Order::where('user_id', $user->id)
                    //     ->where('product_id', $product->id)
                    //     ->where('status', 'paid')
                    //     ->exists();

                    // 若已购，解封发货资产
                    if ($hasPurchased && $product->detail) {
                        $product->detail->makeVisible('delivery_content');
                    }
                }

                $mainActor = $product->actors->first();

                return [
                    'id' => $product->id,
                    'title' => $product->title,
                    'slug' => $product->slug,
                    'type' => $product->type->value,
                    'thumbnail' => $product->list_img,
                    'preview_type' => $product->preview_type->value,
                    'preview_data' => $product->preview_data,
                    'duration' => $product->duration_formatted,
                    'resolution' => $product->resolution ? strtoupper($product->resolution) : null,
                    'spec_badge' => $product->spec_badge,
                    'price' => $product->price,
                    'original_price' => $product->original_price,
                    'has_discount' => $product->has_discount,
                    'views_count' => $product->views_count,
                    'sales_count' => $product->sales_count,
                    'created_at_human' => $product->created_at->diffForHumans(),
                    'publish_date' => $product->created_at->format('Y-m-d'),
                    'has_purchased' => $hasPurchased,
                    'author' => [
                        'name' => $mainActor?->name ?? '官方自营',
                        'avatar' => $mainActor?->avatar ?? null,
                        'title' => '认证创作者',
                        'bio' => '专注于高质量数字资产与专业课程研发。',
                    ],
                    'tags' => $product->tags->map(fn($tag) => [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'name_zh' => $tag->name_zh,
                    ]),
                    'detail' => $product->detail ? [
                        'specs' => $product->detail->specs ?? [],
                        'content' => $product->detail->content,
                        'delivery_type' => $product->detail->delivery_type->value,
                        'delivery_summary' => $product->detail->getDeliverySummary(),
                        'delivery_content' => $hasPurchased ? $product->detail->delivery_content : null,
                    ] : null,
                ];
            }),
        ]);
    }
}
