<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class VideosController extends Controller
{

    public function index()
    {
        return Inertia::render('videos/index');
    }
}
