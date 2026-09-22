<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 固定充值面额配置（单位：分）
    |--------------------------------------------------------------------------
    */
    'deposit_amounts' => [
        [
            'amount' => 10,           // 真实金额：10 分 (即 0.10 元)
            'tissues' => 0.1,          // 获得代币：0.1 纸巾
            'bonus' => 0,            // 额外赠送：0 纸巾
            'label' => '0.1 纸巾',   // 前端展示文本
            'popular' => false,        // 是否主推/热门标签
        ],
        ['amount' => 100, 'tissues' => 1, 'bonus' => 0, 'label' => '1 纸巾', 'popular' => false],
        ['amount' => 1000, 'tissues' => 10, 'bonus' => 0, 'label' => '10 纸巾', 'popular' => false],
        ['amount' => 3000, 'tissues' => 30, 'bonus' => 0, 'label' => '30 纸巾', 'popular' => false],
        ['amount' => 5000, 'tissues' => 50, 'bonus' => 0, 'label' => '50 纸巾', 'popular' => true],
        ['amount' => 10000, 'tissues' => 100, 'bonus' => 0, 'label' => '100 纸巾', 'popular' => false],
        ['amount' => 20000, 'tissues' => 200, 'bonus' => 20, 'label' => '200 纸巾', 'popular' => false],
        ['amount' => 50000, 'tissues' => 500, 'bonus' => 50, 'label' => '500 纸巾', 'popular' => false],
    ],

    /*
    |--------------------------------------------------------------------------
    | 充值支付渠道（已配置各渠道 Logo 资源路径）
    |--------------------------------------------------------------------------
    */
    'payment_methods' => [
        [
            'id' => 'alipay',
            'name' => '支付宝',
            'code' => 'alipay',
            'description' => '支持支付宝扫码、快捷支付',
            'logo' => rtrim((string) env('CDN_URL', ''), '/') . '/images/wallet/alipay.svg',
            'is_active' => true,
            'recommended' => true,
        ],
        [
            'id' => 'wechat',
            'name' => '微信支付',
            'code' => 'wechat_pay',
            'description' => '支持微信 App 快捷扫码或移动端拉起支付',
            'logo' => rtrim((string) env('CDN_URL', ''), '/') . '/images/wallet/wechat.svg',
            'is_active' => true,
            'recommended' => false,
        ],
    ],


];
