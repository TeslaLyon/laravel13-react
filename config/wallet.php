<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 固定充值面额配置（单位：分）
    |--------------------------------------------------------------------------
    */
    'deposit_amounts' => [
        ['amount' => 100, 'tissues' => 1, 'label' => '1 纸巾', 'popular' => false],
        ['amount' => 1000, 'tissues' => 10, 'label' => '10 纸巾', 'popular' => false],
        ['amount' => 3000, 'tissues' => 30, 'label' => '30 纸巾', 'popular' => false],
        ['amount' => 5000, 'tissues' => 50, 'label' => '50 纸巾', 'popular' => true],
        ['amount' => 10000, 'tissues' => 100, 'label' => '100 纸巾', 'popular' => false],
        ['amount' => 20000, 'tissues' => 200, 'label' => '200 纸巾', 'popular' => false],
        ['amount' => 50000, 'tissues' => 500, 'label' => '500 纸巾', 'popular' => false],
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
            'logo' => '/storage/images/wallet/alipay.svg',
            'is_active' => true,
            'recommended' => true,
        ],
        [
            'id' => 'wechat',
            'name' => '微信支付',
            'code' => 'wechat_pay',
            'description' => '支持微信 App 快捷扫码或移动端拉起支付',
            'logo' => '/storage/images/wallet/wechat.svg',
            'is_active' => true,
            'recommended' => false,
        ],
    ],


];
