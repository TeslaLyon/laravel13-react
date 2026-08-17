import React, { useState, useRef, useEffect } from 'react';
import {
    Play,
    Pause,
    Volume2,
    VolumeX,
    Maximize,
    Minimize,
} from 'lucide-react';

// 图片元数据接口定义
export interface ImageMeta {
    src: string;
    webp?: {
        src: string;
        src_source?: string;
        placeholder?: string;
    };
    media?: string;
    width?: number;
    height?: number;
    src_source?: string;
    placeholder?: string;
}

interface VideoHeaderProps {
    /** 图片响应式元数据列表 */
    imgMetaList?: ImageMeta[];
    /** 视频链接 */
    videoUrl?: string;
    /** 视频标题 */
    title?: string;
}

export const VideoHeader: React.FC<VideoHeaderProps> = ({
    imgMetaList = [],
    videoUrl,
    title = 'Video poster',
}) => {
    const videoRef = useRef<HTMLVideoElement | null>(null);
    const containerRef = useRef<HTMLDivElement | null>(null);

    // 播放器状态管理
    const [hasStarted, setHasStarted] = useState(false); // 是否已点击播放
    const [isPlaying, setIsPlaying] = useState(false);   // 是否正在播放
    const [currentTime, setCurrentTime] = useState(0);   // 当前播放时间(秒)
    const [duration, setDuration] = useState(0);         // 视频总时长(秒)
    const [isMuted, setIsMuted] = useState(false);       // 是否静音
    const [isFullscreen, setIsFullscreen] = useState(false);// 是否全屏
    const [showControls, setShowControls] = useState(true); // 控制条显隐

    // 获取默认备用图片（列表最后一项，通常规格最高清）
    const defaultImg = imgMetaList?.[imgMetaList.length - 1]?.src || '';

    // 时间格式化工具：例如 125秒 -> "02:05"
    const formatTime = (seconds: number) => {
        if (isNaN(seconds) || seconds === 0) return '00:00';
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    };

    // 1. 播放/暂停 切换
    const togglePlay = () => {
        if (!videoRef.current) return;

        if (!hasStarted) {
            setHasStarted(true);
        }

        if (isPlaying) {
            videoRef.current.pause();
        } else {
            videoRef.current.play();
        }
        setIsPlaying(!isPlaying);
    };

    // 2. 监听视频播放时间更新
    const handleTimeUpdate = () => {
        if (videoRef.current) {
            setCurrentTime(videoRef.current.currentTime);
        }
    };

    // 3. 视频元数据加载完成，获取视频总时长
    const handleLoadedMetadata = () => {
        if (videoRef.current) {
            setDuration(videoRef.current.duration);
        }
    };

    // 4. 进度条拖动跳转
    const handleSeek = (e: React.ChangeEvent<HTMLInputElement>) => {
        const time = parseFloat(e.target.value);
        if (videoRef.current) {
            videoRef.current.currentTime = time;
            setCurrentTime(time);
        }
    };

    // 5. 切换静音状态
    const toggleMute = () => {
        if (videoRef.current) {
            videoRef.current.muted = !isMuted;
            setIsMuted(!isMuted);
        }
    };

    // 6. 切换全屏状态
    const toggleFullscreen = () => {
        if (!containerRef.current) return;

        if (!document.fullscreenElement) {
            containerRef.current.requestFullscreen().then(() => {
                setIsFullscreen(true);
            }).catch((err) => {
                console.error('全屏失败:', err);
            });
        } else {
            document.exitFullscreen().then(() => {
                setIsFullscreen(false);
            });
        }
    };

    // 监听键盘 ESC 等原生的全屏状态退出
    useEffect(() => {
        const handleFullscreenChange = () => {
            setIsFullscreen(!!document.fullscreenElement);
        };
        document.addEventListener('fullscreenchange', handleFullscreenChange);
        return () => {
            document.removeEventListener('fullscreenchange', handleFullscreenChange);
        };
    }, []);

    return (
        <div
            ref={containerRef}
            className="relative w-full aspect-video bg-black sm:rounded-2xl overflow-hidden shadow-sm group select-none flex items-center justify-center"
            onMouseEnter={() => setShowControls(true)}
            onMouseLeave={() => isPlaying && setShowControls(false)}
        >
            {/* ===== 核心修复点：大图/封面图展示区域 ===== */}
            {(!hasStarted || !videoUrl) && imgMetaList && imgMetaList.length > 0 && (
                <picture className="block absolute inset-0 w-full h-full z-10">
                    {/* WebP 响应式图片源 */}
                    {imgMetaList.map((item, index) =>
                        item.webp?.src ? (
                            <source
                                key={`webp-${index}`}
                                type="image/webp"
                                srcSet={item.webp.src}
                                media={item.media}
                            />
                        ) : null
                    )}
                    {/* JPG/PNG 响应式图片源 */}
                    {imgMetaList.map((item, index) =>
                        item.src ? (
                            <source
                                key={`src-${index}`}
                                srcSet={item.src}
                                media={item.media}
                            />
                        ) : null
                    )}
                    {/* 兜底 img 标签 */}
                    <img
                        src={defaultImg}
                        alt={title}
                        className="w-full h-full object-cover"
                        loading="eager"
                    />
                </picture>
            )}

            {/* ===== 原生 `<video>` 视频元素 ===== */}
            {videoUrl && (
                <video
                    ref={videoRef}
                    src={videoUrl}
                    playsInline
                    className={`w-full h-full object-cover ${hasStarted ? 'block' : 'hidden'}`}
                    onTimeUpdate={handleTimeUpdate}
                    onLoadedMetadata={handleLoadedMetadata}
                    onEnded={() => setIsPlaying(false)}
                    onClick={togglePlay}
                />
            )}

            {/* ===== 居中播放/暂停大按钮 ===== */}
            {videoUrl && (!isPlaying || !hasStarted) && (
                <button
                    onClick={togglePlay}
                    className="absolute z-20 p-4 rounded-full bg-black/60 text-white hover:bg-black/80 hover:scale-110 transition-all duration-200 backdrop-blur-sm cursor-pointer"
                    aria-label="Play video"
                >
                    <Play className="w-8 h-8 fill-current translate-x-0.5" />
                </button>
            )}

            {/* ===== 底部控制栏 ===== */}
            {videoUrl && hasStarted && (
                <div
                    className={`absolute bottom-0 inset-x-0 z-20 bg-gradient-to-t from-black/80 via-black/40 to-transparent p-4 flex flex-col gap-2 transition-opacity duration-300 ${showControls ? 'opacity-100' : 'opacity-0 pointer-events-none'
                        }`}
                >
                    {/* 1. 进度条 */}
                    <div className="relative flex items-center w-full group/slider cursor-pointer">
                        <input
                            type="range"
                            min={0}
                            max={duration || 100}
                            value={currentTime}
                            onChange={handleSeek}
                            className="w-full h-1.5 bg-white/30 rounded-lg appearance-none cursor-pointer accent-white focus:outline-none group-hover/slider:h-2 transition-all"
                        />
                    </div>

                    {/* 2. 操作按钮与时间 */}
                    <div className="flex items-center justify-between text-white text-sm font-medium">
                        <div className="flex items-center gap-3">
                            <button
                                onClick={togglePlay}
                                className="hover:text-neutral-300 transition-colors focus:outline-none cursor-pointer"
                            >
                                {isPlaying ? (
                                    <Pause className="w-5 h-5 fill-current" />
                                ) : (
                                    <Play className="w-5 h-5 fill-current" />
                                )}
                            </button>

                            <button
                                onClick={toggleMute}
                                className="hover:text-neutral-300 transition-colors focus:outline-none cursor-pointer"
                            >
                                {isMuted ? (
                                    <VolumeX className="w-5 h-5" />
                                ) : (
                                    <Volume2 className="w-5 h-5" />
                                )}
                            </button>

                            <span className="text-xs text-white/80 select-none font-mono">
                                {formatTime(currentTime)} / {formatTime(duration)}
                            </span>
                        </div>

                        <div className="flex items-center gap-3">
                            <button
                                onClick={toggleFullscreen}
                                className="hover:text-neutral-300 transition-colors focus:outline-none cursor-pointer"
                            >
                                {isFullscreen ? (
                                    <Minimize className="w-5 h-5" />
                                ) : (
                                    <Maximize className="w-5 h-5" />
                                )}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};
