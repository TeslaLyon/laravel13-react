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
                            {--limit=50 : 批量处理视频数量上限 (默认 50，0 为不限)}
                            {--delay=1 : 每次请求间隔延迟秒数 (默认 1 秒，防被第三方风控)}
                            {--proxy= : 指定 HTTP/SOCKS5 代理 (例如 http://127.0.0.1:7890)}
                            {--force : 强制覆盖已有剧照截图数据的视频}
                            {--dry-run : 演练模式：仅检索和匹配预览，不实际写入数据库}
                            {--debug : 调试排查模式：输出详细搜索词与抓取明细}';

    /**
     * 控制台命令描述
     *
     * @var string
     */
    protected $description = '【兼容别名】向第三方相册源 (Ultra4kPorn / Perfect-Stockings) 爬取 DorcelClub 视频片段剧照';
}
