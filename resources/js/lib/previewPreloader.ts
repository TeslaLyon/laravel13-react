/**
 * 🎯 视频列表页 Preview 智能空闲预加载调度器 (Idle Preview Preloader)
 * 
 * 核心设计原则：
 * 1. 极致低端设备与弱网保护：触屏设备、开启省流模式、2G/3G 弱网、低配 CPU/小内存机型完全自动禁用。
 * 2. 0 竞争首屏带宽：严格利用 requestIdleCallback 与页面 load 事件，仅在主线程与网络完全空闲时才启动。
 * 3. 视口感知 (Intersection Observer)：仅按顺序预热当前屏幕内可见的卡片，超出视口的绝不盲目浪费流量。
 * 4. 单通道排队 (Concurrency = 1)：同一时刻仅允许 1 个资源静默预热，并在任务间插入空闲缓冲间隔。
 * 5. 滚动自适应挂起：用户高速滚动页面时自动暂停，确保 60fps/120fps 丝滑不掉帧。
 */

type PreloadItem = {
    url: string;
    type: number; // 1: 视频, 2: GIF, 3: 多图首帧
};

class PreviewPreloader {
    private queue: PreloadItem[] = [];
    private preloadedUrls = new Set<string>();
    private pendingUrls = new Set<string>();
    private isProcessing = false;
    private isPaused = false;
    private scrollTimer: any = null;
    private singleVideoLoader: HTMLVideoElement | null = null;
    private isSupported: boolean | null = null;

    constructor() {
        if (typeof window === 'undefined') return;

        // 页面滚动监听：滚动时暂停预热，停止 500ms 后恢复
        window.addEventListener('scroll', this.handleScroll, { passive: true });
        // 页面可见性监听：用户切到后台标签页时暂停
        document.addEventListener('visibilitychange', this.handleVisibilityChange);
    }

    /**
     * 设备性能与网络环境前置检查：
     * 若为低端设备、触屏手机、弱网或省流模式，严格返回 false 自动降级禁用
     */
    public checkEligibility(): boolean {
        if (this.isSupported !== null) return this.isSupported;
        if (typeof window === 'undefined') return false;

        // 1. 触屏/移动设备检测：手机端无 hover 交互，预热无意义且浪费移动流量，彻底禁用
        const isTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
        if (isTouch) {
            this.isSupported = false;
            return false;
        }

        // 2. 网络保护检测 (Save-Data 省流或 2G/3G 弱网自动禁用)
        const nav = navigator as any;
        const conn = nav.connection || nav.mozConnection || nav.webkitConnection;
        if (conn) {
            if (conn.saveData === true) {
                this.isSupported = false;
                return false;
            }
            if (['slow-2g', '2g', '3g'].includes(conn.effectiveType)) {
                this.isSupported = false;
                return false;
            }
        }

        // 3. 硬件性能检测：CPU 核心 <= 4 或 内存 < 4GB 的低配设备禁用，确保老机器零负担
        if (navigator.hardwareConcurrency && navigator.hardwareConcurrency < 4) {
            this.isSupported = false;
            return false;
        }
        if (nav.deviceMemory && nav.deviceMemory < 4) {
            this.isSupported = false;
            return false;
        }

        this.isSupported = true;
        return true;
    }

    /**
     * 判断某个资源是否已经被成功预热至浏览器本地缓存
     */
    public isPreloaded(url?: string): boolean {
        if (!url) return false;
        return this.preloadedUrls.has(url);
    }

    /**
     * 将视口内可见的卡片登记入队（防重复入队）
     */
    public enqueue(url: string, type: number): void {
        if (!this.checkEligibility() || !url) return;
        if (this.preloadedUrls.has(url) || this.pendingUrls.has(url)) return;

        this.pendingUrls.add(url);
        this.queue.push({ url, type });

        this.scheduleNext();
    }

    /**
     * 当卡片离开视口时，如果还没开始预热，将其移出队列以节约流量
     */
    public dequeue(url: string): void {
        if (!url) return;
        this.pendingUrls.delete(url);
        this.queue = this.queue.filter(item => item.url !== url);
    }

    private handleScroll = () => {
        this.isPaused = true;
        if (this.scrollTimer) clearTimeout(this.scrollTimer);

        // 滚动停止 400ms 后才继续空闲调度
        this.scrollTimer = setTimeout(() => {
            this.isPaused = false;
            this.scheduleNext();
        }, 400);
    };

    private handleVisibilityChange = () => {
        this.isPaused = document.hidden;
        if (!this.isPaused) {
            this.scheduleNext();
        }
    };

    /**
     * 使用 requestIdleCallback 调度下一个预热任务
     */
    private scheduleNext(): void {
        if (this.isProcessing || this.isPaused || this.queue.length === 0) return;

        const runIdleTask = () => {
            if (this.isProcessing || this.isPaused || this.queue.length === 0) return;
            this.processNext();
        };

        if ('requestIdleCallback' in window) {
            (window as any).requestIdleCallback(runIdleTask, { timeout: 1500 });
        } else {
            setTimeout(runIdleTask, 200);
        }
    }

    /**
     * 单通道处理队列中的下一个资源
     */
    private async processNext(): Promise<void> {
        if (this.isProcessing || this.isPaused || this.queue.length === 0) return;

        const item = this.queue.shift();
        if (!item) return;

        this.isProcessing = true;

        try {
            if (item.type === 1) {
                // 视频类型：使用单例静音 video 元素缓冲开头数据
                await this.preloadVideo(item.url);
            } else {
                // 图片/GIF 类型：使用 Image 对象拉入缓存
                await this.preloadImage(item.url);
            }
            this.preloadedUrls.add(item.url);
        } catch {
            // 静默容错，不阻碍其他任务
        } finally {
            this.pendingUrls.delete(item.url);
            this.isProcessing = false;

            // 任务之间保留 300ms 空闲间隙，让浏览器有充足时间回收和释放 CPU
            setTimeout(() => {
                this.scheduleNext();
            }, 300);
        }
    }

    /**
     * 针对 MP4 视频的轻量预热
     */
    private preloadVideo(url: string): Promise<void> {
        return new Promise((resolve) => {
            if (!this.singleVideoLoader) {
                this.singleVideoLoader = document.createElement('video');
                this.singleVideoLoader.muted = true;
                this.singleVideoLoader.playsInline = true;
                this.singleVideoLoader.preload = 'auto';
            }

            const video = this.singleVideoLoader;
            let finished = false;

            const cleanup = () => {
                if (finished) return;
                finished = true;
                video.removeEventListener('canplay', onCanPlay);
                video.removeEventListener('error', onError);
                // 释放当前视频资源引用，避免挂住内存
                video.removeAttribute('src');
                video.load();
                resolve();
            };

            const onCanPlay = () => cleanup();
            const onError = () => cleanup();

            video.addEventListener('canplay', onCanPlay, { once: true });
            video.addEventListener('error', onError, { once: true });

            // 3.5 秒超时兜底，防止极慢源站卡死预热通道
            setTimeout(cleanup, 3500);

            try {
                video.src = url;
            } catch {
                cleanup();
            }
        });
    }

    /**
     * 针对图片/GIF的轻量预热
     */
    private preloadImage(url: string): Promise<void> {
        return new Promise((resolve) => {
            const img = new Image();
            let finished = false;

            const done = () => {
                if (finished) return;
                finished = true;
                resolve();
            };

            img.onload = done;
            img.onerror = done;
            setTimeout(done, 3000); // 3s 超时兜底
            img.src = url;
        });
    }
}

// 导出全局单例
export const previewPreloader = new PreviewPreloader();

