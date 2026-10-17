<?php

namespace App\Console\Commands;

use App\Console\Commands\Crawler\CrawlDorcelScreenImgCommand;

/**
 * 历史旧命令别名兼容桥接类
 */
class dorcelVideoScreenImg extends CrawlDorcelScreenImgCommand
{
    /**
     * 控制台命令签名 (兼容旧签名)
     *
     * @var string
     */
    protected $signature = 'dorcel:screen-img-u4p
                            {--video-id= : 指定单个视频 ID 进行定向排查与补充}
                            {--all : 全量处理所有 DorcelClub 视频 (等同于 --limit=0)}
                            {--limit=50 : 批量处理视频数量上限 (默认 50，0 为不限)}
                            {--delay=1 : 每次请求间隔延迟秒数 (默认 1 秒，防被第三方风控)}
                            {--proxy= : 指定 HTTP/SOCKS5 代理 (例如 http://127.0.0.1:7890)}
                            {--force : 强制覆盖已有剧照截图数据的视频}
                            {--cooldown-days=7 : 未搜到剧照时加入冷静期的天数 (默认 7 天，设为 0 则不记录冷静期)}
                            {--ignore-cooldown : 忽略 Redis 冷静期限制，强行检查所有符合条件的视频}
                            {--clear-cooldown : 一键清空所有现存的剧照抓取冷静期缓存标记}
                            {--clear-cooldown-id= : 清除指定视频 ID 的剧照抓取冷静期缓存标记}
                            {--count-cooldown : 快速查看当前正处于剧照冷静期中的视频总量}
                            {--export-unmatched= : 将未匹配到剧照的视频清单导出至指定文本文件 (例如 unmatched.txt)}
                            {--order-by=release_at : 排序规则：release_at (最新发布日期优先) 或 id (按数据库自增ID降序)}
                            {--no-telegram : 禁用 Telegram 通知推送 (默认当 .env 配置了 Token 时自动推送)}
                            {--queue : 异步投递至 Horizon 队列后台执行}
                            {--dry-run : 演练模式：仅检索和匹配预览，不实际写入数据库}
                            {--debug : 调试排查模式：输出详细搜索词与抓取明细}';

    /**
     * 控制台命令描述
     *
     * @var string
     */
    protected $description = '【兼容别名】向第三方相册源 (Ultra4kPorn / Perfect-Stockings) 爬取 DorcelClub 视频片段剧照';
}
