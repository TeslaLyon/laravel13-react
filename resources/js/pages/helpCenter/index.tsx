import React from 'react';
import { Head, Link } from '@inertiajs/react';
import {
    Search,
    User,
    CreditCard,
    MonitorPlay,
    ShieldCheck,
    Settings,
    HelpCircle,
    ArrowRight
} from 'lucide-react';

// 引入 shadcn 组件 (请根据你的实际路径调整别名 @/...)
import { Input } from '@/components/ui/input';
import { Card, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
import { Button } from '@/components/ui/button';
import { category as categoryIndex } from '@/actions/App/Http/Controllers/HelpCenterController';

// --- 类型定义 ---
interface Category {
    id: string;
    title: string;
    description: string;
    icon: keyof typeof IconMap;
}

interface FAQ {
    id: string;
    question: string;
    answer: string;
}

interface HelpCenterProps {
    categories?: Category[];
    faqs?: FAQ[];
}

// 图标映射表，方便根据数据动态渲染图标
const IconMap = {
    User,
    CreditCard,
    MonitorPlay,
    ShieldCheck,
    Settings,
    HelpCircle
};

export default function HelpCenter({ categories, faqs }: HelpCenterProps) {
    // 默认演示数据 (如果后端没有传递 props，则使用这些数据)
    const displayCategories = categories || [
        { id: '1', title: '账户与个人资料', description: '管理您的账号设置、密码及安全隐私。', icon: 'User' },
        { id: '2', title: '订阅与账单', description: '查看支付历史、发票及管理您的订阅计划。', icon: 'CreditCard' },
        { id: '3', title: '内容与播放', description: '解决视频播放、清晰度及离线下载相关问题。', icon: 'MonitorPlay' },
        { id: '4', title: '隐私与安全', description: '了解我们如何保护您的数据以及相关政策。', icon: 'ShieldCheck' },
        { id: '5', title: '通用设置', description: '通知偏好、语言设置及辅助功能选项。', icon: 'Settings' },
        { id: '6', title: '其他问题', description: '没有找到对应的分类？在这里寻找更多帮助。', icon: 'HelpCircle' },
    ];

    const displayFaqs = faqs || [
        { id: 'q1', question: '如何重置我的登录密码？', answer: '在登录页面点击“忘记密码”，输入您的注册邮箱。我们会向您发送一封包含密码重置链接的邮件。请在24小时内点击该链接完成重置。' },
        { id: 'q2', question: '我可以与他人共享我的订阅账号吗？', answer: '为了保证您的账户安全和个人推荐的准确性，我们建议每个账户仅限个人使用。分享账号可能会导致您的账户被系统暂时锁定。' },
        { id: 'q3', question: '为什么视频在播放时会卡顿？', answer: '这通常是由于网络连接不稳定造成的。请尝试：1. 切换至分辨率较低的画质；2. 重启您的路由器；3. 检查是否有其他设备正在占用大量带宽。' },
        { id: 'q4', question: '如何申请退款？', answer: '如果您符合我们的退款政策要求（通常为购买后7天内未使用核心服务），请滚动至页面底部联系我们的客服团队，我们将为您人工处理。' },
    ];

    return (
        <>
            <Head title="帮助中心" />

            {/* 主容器：
        使用 max-w-5xl 限制最大宽度，
        大号的纵向间距 (space-y-16) 营造 Apple 风格的呼吸感
      */}
            <div className="max-w-5xl mx-auto px-4 py-16 sm:py-24 space-y-16 sm:space-y-24">

                {/* 1. Hero 区域 & 搜索框 (类 Apple 风格) */}
                <section className="text-center space-y-8">
                    <h1 className="text-4xl sm:text-5xl font-semibold tracking-tight text-slate-900">
                        我们能为您提供什么帮助？
                    </h1>
                    <div className="max-w-2xl mx-auto relative group">
                        <Search className="absolute left-5 top-1/2 -translate-y-1/2 text-slate-400 w-6 h-6 transition-colors group-focus-within:text-blue-500" />
                        <Input
                            className="w-full pl-14 pr-6 h-16 text-lg rounded-2xl bg-slate-50 border-transparent hover:border-slate-200 focus-visible:ring-4 focus-visible:ring-blue-500/20 focus-visible:border-blue-500 transition-all shadow-sm"
                            placeholder="描述您遇到的问题，例如“如何修改密码”..."
                        />
                    </div>
                </section>

                {/* 2. 帮助分类网格 (类 YouTube 极简卡片) */}
                <section>
                    <h2 className="text-2xl font-semibold mb-8 tracking-tight text-slate-900">浏览帮助主题</h2>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        {displayCategories.map((category) => {
                            const IconComponent = IconMap[category.icon];
                            return (
                                <Link href={categoryIndex.url(category.id)} key={category.id}>
                                    <Card
                                        key={category.id}
                                        className="rounded-2xl border-slate-100 shadow-sm hover:shadow-md hover:border-slate-200 transition-all cursor-pointer group"
                                    >
                                        <CardHeader>
                                            <div className="w-12 h-12 rounded-xl bg-slate-50 flex items-center justify-center mb-4 group-hover:bg-blue-50 transition-colors">
                                                <IconComponent className="w-6 h-6 text-slate-600 group-hover:text-blue-600" />
                                            </div>
                                            <CardTitle className="text-xl font-medium">{category.title}</CardTitle>
                                            <CardDescription className="text-slate-500 text-base mt-2">
                                                {category.description}
                                            </CardDescription>
                                        </CardHeader>
                                    </Card>
                                </Link>
                            );
                        })}
                    </div>
                </section>

                {/* 3. 基础 Q&A 手风琴组件 */}
                <section className="max-w-3xl mx-auto">
                    <h2 className="text-2xl font-semibold mb-8 tracking-tight text-center text-slate-900">
                        常见问题解答
                    </h2>
                    <Accordion type="single" collapsible className="w-full">
                        {displayFaqs.map((faq) => (
                            <AccordionItem key={faq.id} value={faq.id} className="border-b-slate-100 py-2">
                                <AccordionTrigger className="text-left text-lg font-medium hover:no-underline hover:text-blue-600 transition-colors">
                                    {faq.question}
                                </AccordionTrigger>
                                <AccordionContent className="text-slate-600 text-base leading-relaxed pb-4">
                                    {faq.answer}
                                </AccordionContent>
                            </AccordionItem>
                        ))}
                    </Accordion>
                </section>

                {/* 4. 底部 CTA (Call to Action) */}
                <section className="text-center bg-slate-50 rounded-3xl p-10 sm:p-16 mt-12 border border-slate-100">
                    <div className="max-w-xl mx-auto space-y-6">
                        <h3 className="text-2xl font-semibold tracking-tight text-slate-900">没有找到您需要的答案？</h3>
                        <p className="text-slate-500 text-lg">
                            我们的支持团队随时准备为您提供帮助。请随时与我们取得联系。
                        </p>
                        <Button size="lg" className="rounded-full px-8 py-6 text-base shadow-sm">
                            联系客服团队
                            <ArrowRight className="ml-2 w-4 h-4" />
                        </Button>
                    </div>
                </section>

            </div>
        </>
    );
}
