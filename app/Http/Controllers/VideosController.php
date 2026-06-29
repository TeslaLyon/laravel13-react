<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class VideosController extends Controller
{

    public function index()
    {
        return Inertia::render('videos/index', [
            'breadcrumbs' => [
                ['title' => '首页', 'href' => route('home')],
                ['title' => '推荐视频', 'href' => null], // 当前页没有 URL
            ],
        ]);
    }
}
