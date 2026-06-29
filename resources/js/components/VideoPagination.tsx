import { ChevronLeft, ChevronRight } from "lucide-react";

export function VideoPagination() {
  return (
    // 使用 mt-12 mb-8 保证与上方视频网格有充足的呼吸空间
    <div className="flex items-center justify-center gap-2 mt-16 mb-12">

      {/* 1. Previous 按钮 */}
      {/* 使用 gap-1 贴合图标与文字，hover 时给出极淡的背景反馈 */}
      <button className="flex items-center gap-1 px-3 py-2 text-[15px] font-medium rounded-xl hover:bg-muted transition-colors text-primary">
        <ChevronLeft className="w-4 h-4" />
        Previous
      </button>

      {/* 2. 普通页码 (未选中) */}
      <button className="w-10 h-10 flex items-center justify-center text-[15px] font-medium rounded-xl hover:bg-muted transition-colors text-primary">
        1
      </button>

      {/* 🎯 3. 当前页码 (Active) - 完美复刻截图 */}
      {/* 去掉 bg，仅仅使用 border-border (或 border-slate-200) 来描边 */}
      <button className="w-10 h-10 flex items-center justify-center text-[15px] font-medium rounded-xl border border-slate-200 dark:border-slate-800 transition-colors text-primary">
        2
      </button>

      {/* 4. 普通页码 (未选中) */}
      <button className="w-10 h-10 flex items-center justify-center text-[15px] font-medium rounded-xl hover:bg-muted transition-colors text-primary">
        3
      </button>

      {/* 5. 省略号 (Ellipsis) */}
      <div className="w-10 h-10 flex items-center justify-center text-[15px] font-medium text-primary tracking-widest">
        ...
      </div>

      {/* 6. Next 按钮 */}
      <button className="flex items-center gap-1 px-3 py-2 text-[15px] font-medium rounded-xl hover:bg-muted transition-colors text-primary">
        Next
        <ChevronRight className="w-4 h-4" />
      </button>

    </div>
  );
}
