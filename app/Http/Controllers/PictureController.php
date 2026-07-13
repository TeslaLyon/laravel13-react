<?php

namespace App\Http\Controllers;


use Inertia\Inertia;
class PictureController extends Controller
{
    public function index()
    {
        return Inertia::render('picture/index', [
            'breadcrumbs' => [
                ['title' => '首页', 'href' => route('home')],
                ['title' => '图片', 'href' => null],
            ],
        ]);
    }
}
