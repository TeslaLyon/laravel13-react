<?php

return [
    'base_coins' => 10,

    'milestones' => [
        3 => ['coins' => 20, 'cards' => 0, 'title' => '连签3天宝箱', 'icon' => '🥉'],
        7 => ['coins' => 50, 'cards' => 0, 'title' => '连签7天进阶礼包', 'icon' => '🥈'],
        14 => ['coins' => 100, 'cards' => 0, 'title' => '连签14天豪华礼盒', 'icon' => '🥇'],
        21 => ['coins' => 200, 'cards' => 0, 'title' => '连签21天终极宝藏', 'icon' => '💎'],
        'full_month' => ['coins' => 300, 'cards' => 1, 'title' => '满月全勤王者', 'icon' => '👑'],
    ],

    'make_up' => [
        'enabled' => true,
        'max_per_month' => 20,
        'initial_gift_cards' => 0,
        'max_days_limit' => 30,
    ],
];
