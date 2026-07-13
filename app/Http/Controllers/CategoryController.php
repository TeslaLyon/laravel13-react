<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index()
    {
        return Inertia::render('category/index', [
            'breadcrumbs' => [
                ['title' => '首页', 'href' => route('home')],
                ['title' => '分类', 'href' => null], // 当前页没有 URL
            ],
        ]);
    }
}
