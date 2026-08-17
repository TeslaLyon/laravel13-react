<?php

namespace App\Enums;

enum CountryEnum: string
{
    // 🎯 亚洲地区
    case JAPAN = 'JP'; // 日本
    case CHINA = 'CN'; // 中国
    case TAIWAN = 'TW'; // 中国台湾
    case KOREA = 'KR'; // 韩国
    case THAILAND = 'TH'; // 泰国
    case PHILIPPINES = 'PH'; // 菲律宾

    // 🎯 北美与南美
    case UNITED_STATES = 'US'; // 美国 
    case BRAZIL = 'BR'; // 巴西

    // 🎯 欧洲地区 (常见成人视频/影视生产国)
    case CZECHIA = 'CZ'; // 捷克
    case HUNGARY = 'HU'; // 匈牙利
    case RUSSIA = 'RU'; // 俄罗斯
    case GERMANY = 'DE'; // 德国
    case FRANCE = 'FR'; // 法国
    case UNITED_KINGDOM = 'GB'; // 英国
    case SPAIN = 'ES'; // 西班牙
    case ITALY = 'IT'; // 意大利

    // 🎯 其他
    case OTHER = 'OT'; // 其他地区

    /**
     * 获取中文显示名称
     */
    public function label(): string
    {
        return match ($this) {
            self::JAPAN => '日本',
            self::CHINA => '中国',
            self::TAIWAN => '中国台湾',
            self::KOREA => '韩国',
            self::THAILAND => '泰国',
            self::PHILIPPINES => '菲律宾',

            self::UNITED_STATES => '美国',
            self::BRAZIL => '巴西',

            self::CZECHIA => '捷克',
            self::HUNGARY => '匈牙利',
            self::RUSSIA => '俄罗斯',
            self::GERMANY => '德国',
            self::FRANCE => '法国',
            self::UNITED_KINGDOM => '英国',
            self::SPAIN => '西班牙',
            self::ITALY => '意大利',

            self::OTHER => '其他',
        };
    }

    /**
     * 转换为前端筛选下拉菜单所需的 Key-Value 选项数组
     */
    public static function toSelectOptions(): array
    {
        return array_map(fn($case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ], self::cases());
    }
}