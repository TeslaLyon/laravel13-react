import React, { useState } from 'react';
import { useForm } from '@inertiajs/react'; // 使用 Inertia Form 处理表单
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import {
    Bookmark, Plus, MonitorPlay, Globe, Link2, Lock, ChevronDown, Check
} from 'lucide-react';

interface PlaylistManagerProps {
    videoId: number;
    initialPlaylists?: Array<{
        id: number;
        name: string;
        isPrivate: boolean;
        isSaved: boolean;
    }>;
}

export default function Save({ videoId, initialPlaylists = [] }: PlaylistManagerProps) {
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [isPopoverOpen, setIsPopoverOpen] = useState(false);

    // --- 新建播放列表表单处理 ---
    const { data, setData, post, processing, reset } = useForm({
        title: '',
        privacy: 'private',
        collaboration: false,
    });

    const handleCreateSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post('/playlists', {
            onSuccess: () => {
                setIsDialogOpen(false); // 关闭对话框
                setIsPopoverOpen(false); // 关闭保存下拉框
                reset();
            },
        });
    };

    return (
        <div className="flex items-center gap-2">
            {/* 1. 保存按钮 + 下拉 Popover */}
            <Popover open={isPopoverOpen} onOpenChange={setIsPopoverOpen}>
                <PopoverTrigger asChild>
                    <Button
                        variant="secondary"
                        className="rounded-full bg-[#f2f2f2] hover:bg-[#e5e5e5] text-black font-medium px-4 h-9 flex items-center gap-2 border-none shadow-none"
                    >
                        <Bookmark className="w-5 h-5" />
                        保存
                    </Button>
                </PopoverTrigger>

                <PopoverContent className="w-[320px] p-0 rounded-xl shadow-xl border-none overflow-hidden bg-white" align="start">
                    <div className="p-4 pb-2">
                        <h3 className="text-base font-bold">保存到...</h3>
                    </div>

                    {/* 播放列表滚动区域 */}
                    <div className="max-h-[300px] overflow-y-auto px-1 py-1">
                        <button className="flex items-center justify-between w-full p-3 hover:bg-gray-100 rounded-lg text-left group">
                            <div className="flex items-center gap-3">
                                <div className="w-10 h-8 bg-gray-200 rounded flex items-center justify-center text-gray-500">
                                    <MonitorPlay size={18} />
                                </div>
                                <div className="flex flex-col">
                                    <span className="text-sm font-medium">稍后观看</span>
                                    <span className="text-xs text-gray-500">私享</span>
                                </div>
                            </div>
                            <Bookmark className="w-5 h-5 text-gray-400 group-hover:text-black" />
                        </button>
                    </div>

                    {/* 2. 嵌套的新建播放列表 Dialog */}
                    <div className="p-3 border-t border-gray-100">
                        <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
                            <DialogTrigger asChild>
                                <button className="w-full h-12 rounded-full hover:bg-gray-100 flex items-center justify-center gap-2 font-medium text-sm transition-colors">
                                    <Plus className="w-5 h-5" />
                                    新建播放列表
                                </button>
                            </DialogTrigger>

                            <DialogContent className="sm:max-w-[400px] rounded-[24px] p-6 gap-6 border-none shadow-2xl">
                                <DialogHeader>
                                    <DialogTitle className="text-[22px] font-bold">新建播放列表</DialogTitle>
                                </DialogHeader>

                                <form onSubmit={handleCreateSubmit} className="space-y-6">
                                    {/* 标题输入框 */}
                                    <div className="space-y-2">
                                        <div className="relative border-2 border-gray-200 rounded-xl focus-within:border-blue-600 transition-all p-2">
                                            <label className="text-xs text-gray-500 px-1">标题</label>
                                            <Input
                                                placeholder="选择一个标题"
                                                value={data.title}
                                                onChange={e => setData('title', e.target.value)}
                                                className="border-none focus-visible:ring-0 h-8 text-base placeholder:text-gray-400 p-1"
                                            />
                                        </div>
                                    </div>

                                    {/* 隐私选择器 */}
                                    <div className="space-y-2">
                                        <Select value={data.privacy} onValueChange={v => setData('privacy', v)}>
                                            <SelectTrigger className="w-full h-[60px] rounded-xl border-2 border-gray-200 focus:ring-0 focus:border-blue-600 p-3 text-left flex flex-col items-start justify-center gap-0">
                                                <span className="text-xs text-gray-500">公开范围</span>
                                                <div className="flex items-center justify-between w-full">
                                                    <span className="text-base">{data.privacy === 'private' ? '私享' : data.privacy === 'unlisted' ? '不公开列出' : '公开'}</span>
                                                </div>
                                            </SelectTrigger>
                                            <SelectContent className="rounded-xl p-0 overflow-hidden border-none shadow-2xl">
                                                <div className="p-4 pb-2 text-base font-bold">公开范围</div>

                                                <SelectItem value="public" className="p-4 focus:bg-gray-100 border-t border-gray-50 cursor-pointer">
                                                    <div className="flex items-start gap-4">
                                                        <Globe className="w-6 h-6 mt-1" />
                                                        <div className="flex flex-col">
                                                            <span className="text-base font-medium">公开</span>
                                                            <span className="text-sm text-gray-500 leading-tight">任何人都可以搜索到并观看。需要创建频道。</span>
                                                        </div>
                                                    </div>
                                                </SelectItem>

                                                <SelectItem value="unlisted" className="p-4 focus:bg-gray-100 cursor-pointer">
                                                    <div className="flex items-start gap-4">
                                                        <Link2 className="w-6 h-6 mt-1" />
                                                        <div className="flex flex-col">
                                                            <span className="text-base font-medium">不公开列出</span>
                                                            <span className="text-sm text-gray-500 leading-tight">知道链接的人都可以观看。需要创建频道。</span>
                                                        </div>
                                                    </div>
                                                </SelectItem>

                                                <SelectItem value="private" className="p-4 focus:bg-gray-100 cursor-pointer">
                                                    <div className="flex items-start gap-4">
                                                        <Lock className="w-6 h-6 mt-1" />
                                                        <div className="flex flex-col">
                                                            <span className="text-base font-medium">私享</span>
                                                            <span className="text-sm text-gray-500 leading-tight">只有你可以观看</span>
                                                        </div>
                                                    </div>
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    {/* 协作开关 */}
                                    <div className="flex items-center justify-between py-2">
                                        <span className="text-base font-medium text-gray-800">协作</span>
                                        <Switch
                                            checked={data.collaboration}
                                            onCheckedChange={v => setData('collaboration', v)}
                                            className="data-[state=checked]:bg-blue-600"
                                        />
                                    </div>

                                    {/* 底部按钮 */}
                                    <div className="flex justify-end gap-3 pt-4">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            onClick={() => setIsDialogOpen(false)}
                                            className="rounded-full px-6 font-bold"
                                        >
                                            取消
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={!data.title || processing}
                                            className={`rounded-full px-6 font-bold transition-all shadow-none ${!data.title ? 'bg-gray-100 text-gray-400' : 'bg-gray-100 text-blue-600 hover:bg-gray-200'
                                                }`}
                                        >
                                            创建
                                        </Button>
                                    </div>
                                </form>
                            </DialogContent>
                        </Dialog>
                    </div>
                </PopoverContent>
            </Popover>
        </div>
    );
}
