import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  ListVideo,
  Clock,
  Bookmark,
  Download,
  Share,
  Ban,
  MinusCircle,
  Flag,
  EllipsisVertical
} from "lucide-react";

export function VideoMenu() {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        {/* asChild 允许使用自定义按钮，阻止冒泡防止跳转 */}
        <button
          className="p-1.5 hover:bg-muted rounded-full transition-colors outline-none"
          onClick={(e) => e.preventDefault()}
        >
          <EllipsisVertical className="w-5 h-5 text-primary" />
        </button>
      </DropdownMenuTrigger>

      <DropdownMenuContent align="end" className="w-56 rounded-xl shadow-lg">
        {/* 列表项 1 */}
        <DropdownMenuItem className="gap-3 py-2 cursor-pointer text-[15px]">
          <ListVideo className="w-5 h-5" />
          添加至待播列表
        </DropdownMenuItem>
        <DropdownMenuItem className="gap-3 py-2 cursor-pointer text-[15px]">
          <Clock className="w-5 h-5" />
          保存到“稍后观看”
        </DropdownMenuItem>
        <DropdownMenuItem className="gap-3 py-2 cursor-pointer text-[15px]">
          <Bookmark className="w-5 h-5" />
          保存到播放列表
        </DropdownMenuItem>
        <DropdownMenuItem className="gap-3 py-2 cursor-pointer text-[15px]">
          <Download className="w-5 h-5" />
          下载
        </DropdownMenuItem>
        <DropdownMenuItem className="gap-3 py-2 cursor-pointer text-[15px]">
          <Share className="w-5 h-5" />
          分享
        </DropdownMenuItem>

        {/* 分割线 */}
        <DropdownMenuSeparator className="my-1" />

        {/* 列表项 2 */}
        <DropdownMenuItem className="gap-3 py-2 cursor-pointer text-[15px]">
          <Ban className="w-5 h-5" />
          不感兴趣
        </DropdownMenuItem>
        <DropdownMenuItem className="gap-3 py-2 cursor-pointer text-[15px]">
          <MinusCircle className="w-5 h-5" />
          不要推荐此频道
        </DropdownMenuItem>
        <DropdownMenuItem className="gap-3 py-2 cursor-pointer text-[15px]">
          <Flag className="w-5 h-5" />
          举报
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
