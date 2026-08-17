import React, { useState, useEffect } from 'react';
import { useHttp } from '@inertiajs/react';
import { toast } from 'sonner';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
    DialogFooter,
    DialogDescription,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Edit3, Plus, Trash2 } from 'lucide-react';
import ActorCorrectionController from '@/actions/App/Http/Controllers/ActorCorrectionController';
import { translateActorKey } from '@/utils/translations';

// 🌟 1. 引入鉴权 Hook
import { useRequireAuth } from '@/components/require-auth-provider';

interface CustomField {
    key: string;
    value: string;
    category: 'basic_info' | 'physical_info' | 'socials';
}

interface EditActorDialogProps {
    actor: any;
}

export function EditActorDialog({ actor }: EditActorDialogProps) {
    const [open, setOpen] = useState(false);

    // 🌟 2. 获取 requireAuth 拦截方法
    const { requireAuth } = useRequireAuth();

    // 1. 初始化页面表单状态
    const [formData, setFormData] = useState({
        basic_info: actor?.basic_info || {},
        physical_info: actor?.physical_info || {},
        socials: actor?.socials || {},
        custom_fields: [] as CustomField[],
    });

    // 2. 监听弹窗打开状态，重置数据
    useEffect(() => {
        if (open) {
            setFormData({
                basic_info: { ...(actor?.basic_info || {}) },
                physical_info: { ...(actor?.physical_info || {}) },
                socials: { ...(actor?.socials || {}) },
                custom_fields: [], // 强制清空自定义选项
            });
        }
    }, [open, actor]);

    // 3. 初始化 useHttp
    const { post, processing, transform } = useHttp({
        basic_info: actor?.basic_info || {},
        physical_info: actor?.physical_info || {},
        socials: actor?.socials || {},
        custom_fields: [] as CustomField[],
    });

    // 🌟 3. 接管 Dialog 的打开/关闭逻辑，加入 requireAuth 验证
    const handleOpenChange = (newOpen: boolean) => {
        if (newOpen) {
            // 当用户试图打开弹窗时，进行权限拦截
            requireAuth(() => {
                setOpen(true);
            });
        } else {
            setOpen(false);
        }
    };

    const handleKnownFieldChange = (category: 'basic_info' | 'physical_info' | 'socials', key: string, value: string) => {
        setFormData((prevData) => ({
            ...prevData,
            [category]: {
                ...prevData[category],
                [key]: value,
            },
        }));
    };

    const addCustomField = (category: 'basic_info' | 'physical_info' | 'socials') => {
        setFormData((prevData) => ({
            ...prevData,
            custom_fields: [
                ...prevData.custom_fields,
                { key: '', value: '', category },
            ]
        }));
    };

    const updateCustomField = (index: number, keyOrValue: 'key' | 'value', text: string) => {
        setFormData((prevData) => {
            const updated = [...prevData.custom_fields];
            updated[index][keyOrValue] = text;
            return { ...prevData, custom_fields: updated };
        });
    };

    const removeCustomField = (index: number) => {
        setFormData((prevData) => {
            const updated = prevData.custom_fields.filter((_, i) => i !== index);
            return { ...prevData, custom_fields: updated };
        });
    };

    // 🌟 4. 表单提交逻辑中包裹 requireAuth
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        requireAuth(() => {
            // 提交前覆盖 formData
            transform((data: any) => ({
                ...data,
                ...formData,
            }));

            post(ActorCorrectionController.store.url({ actor: actor.id, slug: actor.slug }), {
                onSuccess: () => {
                    toast.success("修正建议提交成功，感谢您的贡献！", {
                        duration: 8000,
                        description: "我们将在审核后更新演员信息，并发放对应的奖励，再次感谢您的时间和努力！",
                    });
                    setOpen(false);
                },
                onError: () => {
                    toast.error("提交失败，请稍后重试。");
                },
            });
        });
    };

    const handleClose = () => {
        setOpen(false);
    };

    return (
        // 🌟 绑定 handleOpenChange 处理打开事件
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm" className="rounded-full gap-1.5 text-xs border-border hover:bg-muted">
                    <Edit3 className="w-3.5 h-3.5" />
                    <span>修正资料</span>
                </Button>
            </DialogTrigger>

            <DialogContent className="sm:max-w-[600px] max-h-[85vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="text-xl font-bold">修正演员资料 - {actor?.name}</DialogTitle>
                    <DialogDescription className="text-sm text-muted-foreground">
                        发现资料有误或有补充？欢迎提交修正，审核通过后将更新。
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-6 mt-2">
                    <Tabs defaultValue="basic" className="w-full">
                        <TabsList className="grid w-full grid-cols-3 bg-muted/60 p-1 rounded-xl">
                            <TabsTrigger value="basic" className="rounded-lg text-xs">基本信息</TabsTrigger>
                            <TabsTrigger value="physical" className="rounded-lg text-xs">外形数据</TabsTrigger>
                            <TabsTrigger value="socials" className="rounded-lg text-xs">社交媒体</TabsTrigger>
                        </TabsList>

                        {/* ===================== 基本信息 Tab ===================== */}
                        <TabsContent value="basic" className="space-y-4 pt-4">
                            <div className="grid grid-cols-2 gap-4">
                                {Object.entries(formData.basic_info).map(([key, value]) => (
                                    <div key={key}>
                                        <Label className="text-xs font-medium">{translateActorKey(key)}</Label>
                                        <Input
                                            className="mt-1 h-9 text-sm"
                                            value={value as string}
                                            onChange={(e) => handleKnownFieldChange('basic_info', key, e.target.value)}
                                        />
                                    </div>
                                ))}
                            </div>

                            <div className="pt-3 mt-4 space-y-3">
                                {formData.custom_fields.map((field, idx) => field.category === 'basic_info' && (
                                    <div key={idx} className="flex items-center gap-2">
                                        <Input placeholder="属性名 (如: 眼睛颜色)" className="h-9 text-sm flex-1" value={field.key} onChange={(e) => updateCustomField(idx, 'key', e.target.value)} />
                                        <Input placeholder="属性值 (如: 黑色)" className="h-9 text-sm flex-1" value={field.value} onChange={(e) => updateCustomField(idx, 'value', e.target.value)} />
                                        <Button type="button" variant="ghost" size="icon" className="h-9 w-9 shrink-0 text-muted-foreground hover:text-destructive hover:bg-destructive/10" onClick={() => removeCustomField(idx)}>
                                            <Trash2 className="w-4 h-4" />
                                        </Button>
                                    </div>
                                ))}
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => addCustomField('basic_info')}
                                    className="w-full border-dashed border-2 h-10 text-muted-foreground hover:text-foreground transition-colors"
                                >
                                    <Plus className="w-4 h-4 mr-2" />
                                    添加自定义基本信息
                                </Button>
                            </div>
                        </TabsContent>

                        {/* ===================== 外形数据 Tab ===================== */}
                        <TabsContent value="physical" className="space-y-4 pt-4">
                            <div className="grid grid-cols-2 gap-4">
                                {Object.entries(formData.physical_info).map(([key, value]) => (
                                    <div key={key}>
                                        <Label className="text-xs font-medium">{translateActorKey(key)}</Label>
                                        <Input
                                            className="mt-1 h-9 text-sm"
                                            value={value as string}
                                            onChange={(e) => handleKnownFieldChange('physical_info', key, e.target.value)}
                                        />
                                    </div>
                                ))}
                            </div>

                            <div className="pt-3 mt-4 space-y-3">
                                {formData.custom_fields.map((field, idx) => field.category === 'physical_info' && (
                                    <div key={idx} className="flex items-center gap-2">
                                        <Input placeholder="属性名 (如: 腿长)" className="h-9 text-sm flex-1" value={field.key} onChange={(e) => updateCustomField(idx, 'key', e.target.value)} />
                                        <Input placeholder="属性值 (如: 100cm)" className="h-9 text-sm flex-1" value={field.value} onChange={(e) => updateCustomField(idx, 'value', e.target.value)} />
                                        <Button type="button" variant="ghost" size="icon" className="h-9 w-9 shrink-0 text-muted-foreground hover:text-destructive hover:bg-destructive/10" onClick={() => removeCustomField(idx)}>
                                            <Trash2 className="w-4 h-4" />
                                        </Button>
                                    </div>
                                ))}
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => addCustomField('physical_info')}
                                    className="w-full border-dashed border-2 h-10 text-muted-foreground hover:text-foreground transition-colors"
                                >
                                    <Plus className="w-4 h-4 mr-2" />
                                    添加自定义外形数据
                                </Button>
                            </div>
                        </TabsContent>

                        {/* ===================== 社交媒体 Tab ===================== */}
                        <TabsContent value="socials" className="space-y-4 pt-4">
                            <div className="space-y-4">
                                {Object.entries(formData.socials).map(([key, value]) => (
                                    <div key={key}>
                                        <Label className="text-xs font-medium">{translateActorKey(key)}</Label>
                                        <Input
                                            className="mt-1 h-9 text-sm"
                                            value={value as string}
                                            onChange={(e) => handleKnownFieldChange('socials', key, e.target.value)}
                                        />
                                    </div>
                                ))}
                            </div>

                            <div className="pt-3 mt-4 space-y-3">
                                {formData.custom_fields.map((field, idx) => field.category === 'socials' && (
                                    <div key={idx} className="flex items-center gap-2">
                                        <Input placeholder="平台名称 (如: Tiktok)" className="h-9 text-sm flex-[0.7]" value={field.key} onChange={(e) => updateCustomField(idx, 'key', e.target.value)} />
                                        <Input placeholder="主页链接" className="h-9 text-sm flex-[1.3]" value={field.value} onChange={(e) => updateCustomField(idx, 'value', e.target.value)} />
                                        <Button type="button" variant="ghost" size="icon" className="h-9 w-9 shrink-0 text-muted-foreground hover:text-destructive hover:bg-destructive/10" onClick={() => removeCustomField(idx)}>
                                            <Trash2 className="w-4 h-4" />
                                        </Button>
                                    </div>
                                ))}
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => addCustomField('socials')}
                                    className="w-full border-dashed border-2 h-10 text-muted-foreground hover:text-foreground transition-colors"
                                >
                                    <Plus className="w-4 h-4 mr-2" />
                                    添加自定义社交媒体
                                </Button>
                            </div>
                        </TabsContent>
                    </Tabs>

                    <DialogFooter className="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 border-t border-border/60 pt-4 mt-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleClose}
                            disabled={processing}
                            className="w-full sm:w-auto h-11 sm:h-10 transition-colors duration-200"
                        >
                            取消
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="
                                w-full sm:w-auto h-11 sm:h-10
                                bg-orange-600 hover:bg-orange-700 text-white
                                shadow-sm hover:shadow-md
                                transition-all duration-200 ease-in-out
                                focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-600 focus-visible:ring-offset-2
                                disabled:pointer-events-none disabled:opacity-50
                            "
                        >
                            {processing ? "提交中..." : "提交建议"}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}