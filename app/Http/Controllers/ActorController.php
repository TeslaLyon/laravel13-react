<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Actor;
use Illuminate\Http\Request;
use Illuminate\Support\Sleep;

class ActorController extends Controller
{
    public function index()
    {
        $actors = Actor::orderByDesc('created_at')->paginate(9);
        // dd($actors->toJson());
        return Inertia::render('actor/index', [
            'breadcrumbs' => [
                ['title' => '首页', 'href' => route('home')],
                ['title' => '演员', 'href' => null],
            ],
            // 'actors' => Inertia::defer(function () use ($actors) {
            //     Sleep::for(1000)->milliseconds();
            //     return $actors;
            // })

            'actors' => $actors
        ]);
    }

    public function show(Request $request, Actor $actor)
    {
        $user = $request->user();
        $initisFollowed = false;
        if ($user) {
            $initisFollowed = $actor->viaLoveReactant()->isReactedBy($user, 'FollowActor');
        }

        return Inertia::render('actor/show', [
            'breadcrumbs' => [
                ['title' => '首页', 'href' => route('home')],
                ['title' => '演员', 'href' => route('actors.index')],
                ['title' => $actor->name, 'href' => null], // 当前页没有 URL
            ],
            'actor' => $actor,
            'initisFollowed' => $initisFollowed,
        ]);
    }

    public function follow(Request $request, Actor $actor)
    {
        $user = $request->user();
        $reacter = $user->viaLoveReacter();

        $isFollow = false;
        $msg = '操作成功';

        if ($reacter->hasReactedTo($actor, "FollowActor")) {
            $reacter->unreactTo($actor, "FollowActor");
            $isFollow = false;
            $msg = '已取消关注';
        } else {
            $reacter->reactTo($actor, "FollowActor");
            $isFollow = true;
            $msg = '关注成功';
        }

        Sleep::for(2000)->milliseconds();
        return response()->json([
            'status' => $isFollow,
            'message' => $msg,
        ]);
    }

    public function menuStatus(Request $request, Actor $actor)
    {
        $user = $request->user();
        $isFollow = false;
        if ($user) {
            $isFollow = $actor->viaLoveReactant()->isReactedBy($user, "FollowActor");
        }
        Sleep::for(2000)->milliseconds();
        return response()->json([
            'status' => $isFollow,
        ]);
    }
}
