<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class ArticleController extends Controller
{
    public function index()
    {
        return Inertia::render('article/index', [
            'breadcrumbs' => [
                ['title' => '首页', 'href' => route('home')],
                ['title' => '推荐文章', 'href' => null], // 当前页没有 URL
            ],
        ]);
    }
}
