<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LoveReactionTypeSeeder extends Seeder
{
    public function run(): void
    {
        $reactionTypes = [
            ['name' => 'SubscribeChannel', 'mass' => 1],
            ['name' => 'Like', 'mass' => 1],
            ['name' => 'Dislike', 'mass' => -1],
            ['name' => 'VideoCollect', 'mass' => 1],
            ['name' => 'FollowActor', 'mass' => 1],
            ['name' => 'SaveToWatchLater', 'mass' => 1],
            ['name' => 'FollowUser', 'mass' => 1],
            ['name' => 'SubscribeAll', 'mass' => 1],
            ['name' => 'SubscribePersonalized', 'mass' => 1],
            ['name' => 'SubscribeNone', 'mass' => 1],
        ];

        $now = now();

        foreach ($reactionTypes as $type) {
            DB::table('love_reaction_types')->updateOrInsert(
                ['name' => $type['name']],
                [
                    'mass' => $type['mass'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
