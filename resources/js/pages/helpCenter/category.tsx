import React from 'react';
import { Head, Link } from '@inertiajs/react';
import {
    Search,
    ArrowLeft,
    FileText,
    ChevronRight,
    User
} from 'lucide-react';

import { Input } from '@/components/ui/input';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

// --- 类型定义 ---
interface CategoryType {
    id: string;
    title: string;
    description: string;
    icon: string; // 这里暂用字符串，实际可按需映射
}

interface Article {
    id: string;
    title: string;
    excerpt: string;
}

interface CategoryPageProps {
    category?: CategoryType;
    articles?: Article[];
}

export default function CategoryPage({ category, articles }: CategoryPageProps) {
    // 默认演示数据 (如果 Laravel 后端未传递 props)
    const currentCategory = category || {
        id: '1',
        title: '账户与个人资料',
        description: '管理您的账号设置、密码及安全隐私。',
        icon: 'User'
    };

    const currentArticles = articles || [
        { id: 'a1', title: '如何修改我的登录密码？', excerpt: '了解如何通过邮箱验证重置或修改您的账户密码。' },
        { id: 'a2', title: '更新个人资料信息', excerpt: '更改您的头像、昵称以及绑定的联系方式。' },
        { id: 'a3', title: '如何注销我的账户？', excerpt: '账户注销的步骤以及注销后数据的处理方式。' },
        { id: 'a4', title: '管理绑定的第三方账号', excerpt: '绑定或解绑微信、Google、Apple 等快捷登录方式。' },
    ];

    return (
        <>
            <Head title={`${currentCategory.title} - 帮助中心`} />

            <div className="max-w-4xl mx-auto px-4 py-12 sm:py-16 space-y-12">

                {/* 1. 顶部导航与面包屑 (Apple 风格的简洁返回) */}
                <nav className="flex items-center text-sm font-medium text-slate-500">
                    <Link
                        href="/help" // 请替换为你实际的帮助中心首页路由
                        className="flex items-center hover:text-blue-600 transition-colors"
                    >
                        <ArrowLeft className="w-4 h-4 mr-2" />
                        返回帮助中心
                    </Link>
                </nav>

                {/* 2. 分类头部 & 页内搜索 */}
                <header className="space-y-6">
                    <div className="flex items-center space-x-4">
                        <div className="w-14 h-14 rounded-2xl bg-blue-50 flex items-center justify-center">
                            {/* 这里仅作演示，使用固定图标。实际可像首页一样做一个 IconMap */}
                            <User className="w-7 h-7 text-blue-600" />
                        </div>
                        <div>
                            <h1 className="text-3xl sm:text-4xl font-semibold tracking-tight text-slate-900">
                                {currentCategory.title}
                            </h1>
                            <p className="text-slate-500 mt-1 text-lg">
                                {currentCategory.description}
                            </p>
                        </div>
                    </div>

                    {/* 缩小版的分类内搜索 */}
                    <div className="relative group max-w-xl pt-4">
                        <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 w-5 h-5 transition-colors group-focus-within:text-blue-500 mt-2" />
                        <Input
                            className="w-full pl-12 pr-4 h-12 text-base rounded-xl bg-slate-50 border-transparent hover:border-slate-200 focus-visible:ring-4 focus-visible:ring-blue-500/20 focus-visible:border-blue-500 transition-all shadow-sm"
                            placeholder={`在"${currentCategory.title}"中搜索...`}
                        />
                    </div>
                </header>

                {/* 3. 文章列表 (YouTube 风格的列表项) */}
                <section className="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                    <div className="divide-y divide-slate-100">
                        {currentArticles.map((article) => (
                            <Link
                                key={article.id}
                                href={`/help/article/${article.id}`} // 请替换为你的文章详情路由
                                className="group flex items-start sm:items-center p-6 sm:px-8 hover:bg-slate-50 transition-colors block"
                            >
                                <div className="bg-slate-100 rounded-lg p-3 mr-5 group-hover:bg-white group-hover:shadow-sm transition-all hidden sm:block">
                                    <FileText className="w-5 h-5 text-slate-500 group-hover:text-blue-600" />
                                </div>

                                <div className="flex-1 pr-4">
                                    <h3 className="text-lg font-medium text-slate-900 group-hover:text-blue-600 transition-colors">
                                        {article.title}
                                    </h3>
                                    <p className="text-slate-500 text-sm mt-1 line-clamp-1">
                                        {article.excerpt}
                                    </p>
                                </div>

                                <div className="ml-auto pt-1 sm:pt-0">
                                    <ChevronRight className="w-5 h-5 text-slate-300 group-hover:text-blue-500 transition-transform group-hover:translate-x-1" />
                                </div>
                            </Link>
                        ))}
                    </div>
                </section>

                {/* 4. 底部支持提示 */}
                <div className="text-center pt-8">
                    <p className="text-slate-500">
                        没有找到相关文章？
                        <Link href="/contact" className="text-blue-600 font-medium hover:underline ml-2">
                            联系我们的支持团队
                        </Link>
                    </p>
                </div>

            </div>
        </>
    );
}
