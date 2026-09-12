import { Link } from '@inertiajs/react';
import { Fragment } from 'react';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function Breadcrumbs({
    breadcrumbs,
}: {
    breadcrumbs: BreadcrumbItemType[];
}) {
    return (
        <>
            {breadcrumbs.length > 0 && (
                <Breadcrumb>
                    {/* 1. 强制单行不折行 */}
                    <BreadcrumbList className="flex-nowrap">
                        {breadcrumbs.map((item, index) => {
                            const isLast = index === breadcrumbs.length - 1;

                            return (
                                <Fragment key={index}>
                                    {/* 2. 最后一项允许收缩 (min-w-0)，非最后一项保持固定尺寸 (shrink-0) */}
                                    <BreadcrumbItem className={isLast ? "min-w-0" : "shrink-0"}>
                                        {isLast ? (
                                            /* 3. 移动端限宽 130px 截断，平板 260px，桌面端取消限制 */
                                            <BreadcrumbPage
                                                title={item.title}
                                                className="block max-w-[130px] truncate sm:max-w-[260px] md:max-w-none"
                                            >
                                                {item.title}
                                            </BreadcrumbPage>
                                        ) : (
                                            /* 4. 中间链接项也加入防超长截断保护 */
                                            <BreadcrumbLink
                                                asChild
                                                title={item.title}
                                                className="block max-w-[100px] truncate sm:max-w-none"
                                            >
                                                <Link href={item.href}>
                                                    {item.title}
                                                </Link>
                                            </BreadcrumbLink>
                                        )}
                                    </BreadcrumbItem>

                                    {/* 5. 分隔符图标禁止被挤压 */}
                                    {!isLast && <BreadcrumbSeparator className="shrink-0" />}
                                </Fragment>
                            );
                        })}
                    </BreadcrumbList>
                </Breadcrumb>
            )}
        </>
    );
}
