export interface PixelCrop {
    x: number;
    y: number;
    width: number;
    height: number;
}

/**
 * 将图片裁剪区域绘制到 Canvas 并固定输出为 400x400 像素的高清头像
 * 支持图片缩小后的安全边界填充
 */
export default async function getCroppedImg(
    imageSrc: string,
    pixelCrop: PixelCrop,
    fileName = 'avatar.jpg',
    outputSize = 400
): Promise<File> {
    const image = await createImage(imageSrc);
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');

    if (!ctx) {
        throw new Error('无法创建 Canvas 2D 上下文');
    }

    // 🎯 1. 严格锁定输出画布尺寸为 400 x 400
    canvas.width = outputSize;
    canvas.height = outputSize;

    // 🎯 2. 先用纯白背景填充底色 (防止缩小后四周产生黑边)
    ctx.fillStyle = '#FFFFFF';
    ctx.fillRect(0, 0, outputSize, outputSize);

    // 🎯 3. 开启高质量图像插值平滑算法
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'high';

    // 🎯 4. 绘制裁剪区域并等比缩放到 400x400
    ctx.drawImage(
        image,
        pixelCrop.x,
        pixelCrop.y,
        pixelCrop.width,
        pixelCrop.height,
        0,
        0,
        outputSize,
        outputSize
    );

    // 🎯 5. 导出为标准 JPEG
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
            0.85
        );
    });
}

function createImage(url: string): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.addEventListener('load', () => resolve(image));
        image.addEventListener('error', (error) => reject(error));
        image.setAttribute('crossOrigin', 'anonymous');
        image.src = url;
    });
}
