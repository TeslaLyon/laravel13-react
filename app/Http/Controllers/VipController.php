<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class VipController extends Controller
{
    public function index()
    {
        return Inertia::render('vip/index', [
            'breadcrumbs' => [
                ['title' => '首页', 'href' => route('home')],
                ['title' => 'VIP', 'href' => null], // 当前页没有 URL
            ],
        ]);
    }
}
