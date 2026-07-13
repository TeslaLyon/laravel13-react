import React from 'react';

const ImageGallery = ({ list_img }: { list_img: any[] }) => {
    // 如果没有数据，返回 null 或空结构
    if (!list_img || list_img.length === 0) {
        return null;
    }

    return (
        <div className="image-gallery" style={{ display: 'flex', gap: '20px', flexWrap: 'wrap' }}>
            {list_img.map((img, index) => {
                // 构建 WebP 和 JPEG 的 srcSet 字符串
                const webpSrcSet = img.webp
                    ? `${img.webp.src} 1x, ${img.webp.highdpi?.double} 2x`
                    : '';
                const jpegSrcSet = img.highdpi
                    ? `${img.src} 1x, ${img.highdpi.double} 2x`
                    : '';

                return (
                    // 使用 index 作为 key（如果数据中有唯一的 id 最好使用 id）
                    <picture key={index} style={{ position: 'relative', display: 'block' }}>

                        {/* 1. 优先尝试加载 WebP 格式 */}
                        {img.webp && (
                            <source
                                type="image/webp"
                                srcSet={webpSrcSet}
                            />
                        )}

                        {/* 2. 基础 <img> 标签（作为后备，也处理普通的 JPEG 及其高清版） */}
                        <img
                            src={img.src}
                            srcSet={jpegSrcSet}
                            width={img.width}
                            height={img.height}
                            alt={`Gallery Image ${index + 1}`}
                            // 使用占位图作为背景，在真实图片加载完成前显示
                            style={{
                                backgroundImage: `url(${img.placeholder})`,
                                backgroundSize: 'cover',
                                backgroundPosition: 'center',
                                backgroundColor: '#f0f0f0', // 添加一个底色以防占位图加载慢
                                display: 'block'
                            }}
                            // 开启浏览器原生的懒加载优化性能
                            loading="lazy"
                        />
                    </picture>
                );
            })}
        </div>
    );
};

export default ImageGallery;
