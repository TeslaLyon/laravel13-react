<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class StoreController extends Controller
{
    public function index()
    {
        return Inertia::render('store/index', [
            'breadcrumbs' => [
                ['title' => '首页', 'href' => route('home')],
                ['title' => '商城', 'href' => null], // 当前页没有 URL
            ],
        ]);
    }

    public function show()
    {
        return Inertia::render('store/show', [
            'breadcrumbs' => [
                ['title' => '首页', 'href' => route('home')],
                ['title' => '商城', 'href' => route('store.index')],
                ['title' => '商品详情', 'href' => null], // 当前页没有 URL
            ],
        ]);
    }
}
