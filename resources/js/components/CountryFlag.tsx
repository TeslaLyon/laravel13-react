import React, { memo } from 'react';

export type CountryCode =
    | 'JP' | 'CN' | 'TW' | 'KR' | 'TH' | 'PH'
    | 'US' | 'BR'
    | 'CZ' | 'HU' | 'RU' | 'DE' | 'FR' | 'GB' | 'ES' | 'IT'
    | 'OT' | string;

interface CountryFlagProps {
    code?: CountryCode | null;
    showLabel?: boolean;
    className?: string;
}

// 🎯 1. 国家/地区中文名称映射表
const COUNTRY_NAMES: Record<string, string> = {
    JP: '日本',
    CN: '中国',
    TW: '中国台湾',
    KR: '韩国',
    TH: '泰国',
    PH: '菲律宾',
    US: '美国',
    BR: '巴西',
    CZ: '捷克',
    HU: '匈牙利',
    RU: '俄罗斯',
    DE: '德国',
    FR: '法国',
    GB: '英国',
    ES: '西班牙',
    IT: '意大利',
    OT: '其他',
};

// 🎯 2. 全量高质量 4:3 极简 SVG 矢量国旗映射表
const SVG_FLAGS: Record<string, React.ReactNode> = {
    // 🇯🇵 日本
    JP: (
        <svg viewBox="0 0 640 480" className="w-full h-full object-cover">
            <path fill="#fff" d="M0 0h640v480H0z" />
            <circle cx="320" cy="240" r="144" fill="#bc002d" />
        </svg>
    ),

    // 🇨🇳 中国
    CN: (
        <svg viewBox="0 0 640 480" className="w-full h-full object-cover">
            <path fill="#ee1c25" d="M0 0h640v480H0z" />
            {/* 大五角星 */}
            <path fill="#ffff00" d="M120 48l21.2 65.1h68.5l-55.4 40.3 21.2 65.1-55.4-40.3-55.4 40.3 21.2-65.1-55.4-40.3h68.5z" />
            {/* 四颗环绕小星 */}
            <path fill="#ffff00" d="M240 32l6.2 19.1h20.1l-16.3 11.8 6.2 19.1-16.3-11.8-16.3 11.8 6.2-19.1-16.3-11.8h20.1zM288 80l6.2 19.1h20.1l-16.3 11.8 6.2 19.1-16.3-11.8-16.3 11.8 6.2-19.1-16.3-11.8h20.1zM288 160l6.2 19.1h20.1l-16.3 11.8 6.2 19.1-16.3-11.8-16.3 11.8 6.2-19.1-16.3-11.8h20.1zM240 208l6.2 19.1h20.1l-16.3 11.8 6.2 19.1-16.3-11.8-16.3 11.8 6.2-19.1-16.3-11.8h20.1z" />
        </svg>
    ),

    // 🇹🇼 中国台湾
    TW: (
        <svg viewBox="0 0 640 480" className="w-full h-full object-cover">
            <path fill="#fe0000" d="M0 0h640v480H0z" />
            <path fill="#000095" d="M0 0h320v240H0z" />
            <path fill="#fff" d="m160 40 15 42 43-12-15 43 43 14-38 25 26 38-42-15-15 43-15-43-42 15 26-38-38-25 43-14-15-43 43 12z" />
            <circle cx="160" cy="120" r="36" fill="#000095" />
            <circle cx="160" cy="120" r="30" fill="#fff" />
        </svg>
    ),

    // 🇰🇷 韩国
    KR: (
        <svg viewBox="0 0 640 480" className="w-full h-full object-cover">
            <path fill="#fff" d="M0 0h640v480H0z" />
            <circle cx="320" cy="240" r="120" fill="#c60c30" />
            <path fill="#003478" d="M200 240a120 120 0 0 0 120 120 60 60 0 0 0 0-120 60 60 0 0 1 0-120 120 120 0 0 0-120 120z" />
            <g fill="#000">
                <g transform="translate(160, 100) rotate(33.7)">
                    <rect x="-35" y="-20" width="70" height="8" />
                    <rect x="-35" y="-5" width="70" height="8" />
                    <rect x="-35" y="10" width="70" height="8" />
                </g>
                <g transform="translate(480, 380) rotate(33.7)">
                    <rect x="-35" y="-20" width="31" height="8" /><rect x="4" y="-20" width="31" height="8" />
                    <rect x="-35" y="-5" width="31" height="8" /><rect x="4" y="-5" width="31" height="8" />
                    <rect x="-35" y="10" width="31" height="8" /><rect x="4" y="10" width="31" height="8" />
                </g>
                <g transform="translate(480, 100) rotate(-33.7)">
                    <rect x="-35" y="-20" width="70" height="8" />
                    <rect x="-35" y="-5" width="31" height="8" /><rect x="4" y="-5" width="31" height="8" />
                    <rect x="-35" y="10" width="70" height="8" />
                </g>
                <g transform="translate(160, 380) rotate(-33.7)">
                    <rect x="-35" y="-20" width="31" height="8" /><rect x="4" y="-20" width="31" height="8" />
                    <rect x="-35" y="-5" width="70" height="8" />
                    <rect x="-35" y="10" width="31" height="8" /><rect x="4" y="10" width="31" height="8" />
                </g>
            </g>
        </svg>
    ),

    // 🇹🇭 泰国
    TH: (
        <svg viewBox="0 0 640 480" className="w-full h-full object-cover">
            <path fill="#a51931" d="M0 0h640v480H0z" />
            <path fill="#f4f5f8" d="M0 80h640v320H0z" />
            <path fill="#2d2a4a" d="M0 160h640v160H0z" />
        </svg>
    ),

    // 🇵🇭 菲律宾
    PH: (
        <svg viewBox="0 0 640 480" className="w-full h-full object-cover">
            <path fill="#0038a8" d="M0 0h640v240H0z" />
            <path fill="#ce1126" d="M0 240h640v240H0z" />
            <path fill="#fff" d="M0 0l415.7 240L0 480z" />
            <circle cx="130" cy="240" r="45" fill="#fcd116" />
            <path fill="#fcd116" d="M60 70l6 18h19l-15 11 6 18-16-11-16 11 6-18-15-11h19zM60 410l6 18h19l-15 11 6 18-16-11-16 11 6-18-15-11h19zM340 240l6 18h19l-15 11 6 18-16-11-16 11 6-18-15-11h19z" />
        </svg>
    ),

    // 🇺🇸 美国
    US: (
        <svg viewBox="0 0 640 480" className="w-full h-full object-cover">
            <path fill="#bb133e" d="M0 0h640v480H0z" />
            <path stroke="#fff" strokeWidth="37" d="M0 55.5h640M0 129.5h640M0 203.5h640M0 277.5h640M0 351.5h640M0 425.5h640" />
            <path fill="#002147" d="M0 0h256v258.5H0z" />
        </svg>
    ),

    // 🇧🇷 巴西
    BR: (
        <svg viewBox="0 0 640 480" className="w-full h-full object-cover">
            <path fill="#009b3a" d="M0 0h640v480H0z" />
            <path fill="#fedf00" d="M320 50L580 240 320 430 60 240z" />
            <circle cx="320" cy="240" r="120" fill="#002776" />
            <path fill="#fff" d="M198 250a125 125 0 0 1 242-20 120 120 0 0 0-242 20z" />
        </svg>
    ),

    // 🇨🇿 捷克
    CZ: (
        <svg viewBox="0 0 640 480" className="w-full h-full object-cover">
            <path fill="#e30613" d="M0 0h640v480H0z" />
            <path fill="#fff" d="M0 0h640v240H0z" />
            <path fill="#11457e" d="M0 0l320 240L0 480z" />
        </svg>
    ),

    // 🇭🇺 匈牙利
    HU: (
        <svg viewBox="0 0 640 480" className="w-full h-full object-cover">
            <path fill="#ce2939" d="M0 0h640v160H0z" />
            <path fill="#fff" d="M0 160h640v160H0z" />
            <path fill="#477050" d="M0 320h640v160H0z" />
        </svg>
    ),

    // 🇷🇺 俄罗斯
    RU: (
        <svg viewBox="0 0 640 480" className="w-full h-full object-cover">
            <path fill="#fff" d="M0 0h640v160H0z" />
            <path fill="#0039a6" d="M0 160h640v160H0z" />
            <path fill="#d52b1e" d="M0 320h640v160H0z" />
        </svg>
    ),

    // 🇩🇪 德国
    DE: (
        <svg viewBox="0 0 640 480" className="w-full h-full object-cover">
            <path fill="#000" d="M0 0h640v160H0z" />
            <path fill="#dd0000" d="M0 160h640v160H0z" />
            <path fill="#ffce00" d="M0 320h640v160H0z" />
        </svg>
    ),

    // 🇫🇷 法国
    FR: (
        <svg viewBox="0 0 640 480" className="w-full h-full object-cover">
            <path fill="#002395" d="M0 0h213.3v480H0z" />
            <path fill="#fff" d="M213.3 0h213.4v480H213.3z" />
            <path fill="#ed2939" d="M426.7 0H640v480H426.7z" />
        </svg>
    ),

    // 🇬🇧 英国
    GB: (
        <svg viewBox="0 0 640 480" className="w-full h-full object-cover">
            <path fill="#012169" d="M0 0h640v480H0z" />
            <path stroke="#fff" strokeWidth="80" d="M0 0l640 480M640 0L0 480" />
            <path stroke="#C8102E" strokeWidth="50" d="M0 0l640 480M640 0L0 480" />
            <path stroke="#fff" strokeWidth="160" d="M320 0v480M0 240h640" />
            <path stroke="#C8102E" strokeWidth="96" d="M320 0v480M0 240h640" />
        </svg>
    ),

    // 🇪🇸 西班牙
    ES: (
        <svg viewBox="0 0 640 480" className="w-full h-full object-cover">
            <path fill="#aa1523" d="M0 0h640v480H0z" />
            <path fill="#f1bf00" d="M0 120h640v240H0z" />
        </svg>
    ),

    // 🇮🇹 意大利
    IT: (
        <svg viewBox="0 0 640 480" className="w-full h-full object-cover">
            <path fill="#009246" d="M0 0h213.3v480H0z" />
            <path fill="#fff" d="M213.3 0h213.4v480H213.3z" />
            <path fill="#ce2b37" d="M426.7 0H640v480H426.7z" />
        </svg>
    ),

    // 🌐 其他（地球兜底图标）
    OT: (
        <svg viewBox="0 0 640 480" className="w-full h-full object-cover bg-zinc-800">
            <circle cx="320" cy="240" r="140" fill="none" stroke="#a1a1aa" strokeWidth="18" />
            <path fill="none" stroke="#a1a1aa" strokeWidth="14" d="M180 240h280M320 100v280M200 170c40 25 160 25 240 0M200 310c40-25 160-25 240 0" />
        </svg>
    ),
};

export const CountryFlag = memo(({ code, showLabel = false, className = '' }: CountryFlagProps) => {
    // 🎯 空值防错判空：若 code 为 null、undefined、空串，直接返回 null
    if (!code || !code.trim()) {
        return null;
    }

    const formattedCode = code.trim().toUpperCase();
    const flagSvg = SVG_FLAGS[formattedCode] || SVG_FLAGS['OT'];
    const label = COUNTRY_NAMES[formattedCode] || formattedCode;

    return (
        <div className={`inline-flex items-center gap-1.5 bg-transparent ${className}`}>
            {/* 🎯 纯净容器：无任何底色与重叠边框，精准微圆角 */}
            <div className="w-5 h-3.5 rounded-[2px] overflow-hidden shrink-0 flex items-center justify-center bg-transparent">
                {flagSvg}
            </div>

            {showLabel && (
                <span className="text-xs text-white font-medium">{label}</span>
            )}
        </div>
    );
});

CountryFlag.displayName = 'CountryFlag';