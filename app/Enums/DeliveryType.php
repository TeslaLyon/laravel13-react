<?php

namespace App\Enums;

enum DeliveryType: string
{
    case NETDISK = 'netdisk';       // 网盘链接 + 提取码 + 解压密码
    case CARD_KEY = 'card_key';     // 自动发卡/激活码/授权码
    case DOWNLOAD = 'download';     // 站点直链下载 (带防盗链签名)
    case ONLINE_VIEW = 'online_view'; // 在线直接解锁观看
}
