export interface PixelCrop {
    x: number;
    y: number;
    width: number;
    height: number;
}

/**
 * 将图片裁剪区域绘制到 Canvas 并导出为指定尺寸与比例的 File 对象
 * 通用支持：圆形头像 (1:1)、宽幅横幅 (2560 × 424) 及任意长宽比图片
 *
 * @param imageSrc 原图 URL 或 Blob 链接
 * @param pixelCrop react-easy-crop 计算出的真实像素选框坐标
 * @param fileName 导出的文件名称（默认 'cropped.jpg'）
 * @param outputWidth 期望输出的宽度（默认 2560）
 * @param outputHeight 可选：显式指定期望输出的高度；若不传则根据 pixelCrop 比例自动换算
 * @param quality 导出图片质量 (0 ~ 1，默认 0.92)
 * @returns Promise<File> 返回可直接通过 FormData 上传的 File 对象
 */
export default async function getCroppedImg(
    imageSrc: string,
    pixelCrop: PixelCrop,
    fileName = 'cropped.jpg',
    outputWidth = 2560,
    outputHeight?: number,
    quality = 0.92
): Promise<File> {
    const image = await createImage(imageSrc);
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');

    if (!ctx) {
        throw new Error('无法创建 Canvas 2D 上下文');
    }

    // 🎯 1. 动态计算目标高度：显式传入优先，否则按选框比例严格推导
    const targetHeight =
        outputHeight && outputHeight > 0
            ? outputHeight
            : Math.round((pixelCrop.height / pixelCrop.width) * outputWidth);

    // 🎯 2. 设置画布输出分辨率
    canvas.width = outputWidth;
    canvas.height = targetHeight;

    // 🎯 3. 填充纯白背景 (防止透明 PNG 导出 JPEG 时出现黑底或边缘黑边)
    ctx.fillStyle = '#FFFFFF';
    ctx.fillRect(0, 0, outputWidth, targetHeight);

    // 🎯 4. 开启高质量图像插值平滑算法
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'high';

    // 🎯 5. 将选区像素精确绘制并缩放到目标画布尺寸
    ctx.drawImage(
        image,
        pixelCrop.x,
        pixelCrop.y,
        pixelCrop.width,
        pixelCrop.height,
        0,
        0,
        outputWidth,
        targetHeight
    );

    // 🎯 6. 导出为标准 JPEG File 对象
    return new Promise((resolve, reject) => {
        canvas.toBlob(
            (blob) => {
                if (!blob) {
                    reject(new Error('Canvas 导出 Blob 失败'));
                    return;
                }
                const file = new File([blob], fileName, { type: 'image/jpeg' });
                resolve(file);
            },
            'image/jpeg',
            quality
        );
    });
}

/**
 * 辅助函数：异步加载图片并支持跨域资源
 */
function createImage(url: string): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.addEventListener('load', () => resolve(image));
        image.addEventListener('error', (error) => reject(error));
        image.setAttribute('crossOrigin', 'anonymous');
        image.src = url;
    });
}
