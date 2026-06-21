# ==========================================
# 阶段 1: 统一构建阶段 (在 GitHub 原生高速 x86 环境下秒速编译)
# ==========================================
# 🚀 核心优化：加上 --platform=$BUILDPLATFORM，直接借用 GitHub 最强算力！
FROM --platform=$BUILDPLATFORM dunglas/frankenphp:php8.4 AS builder

# 安装系统级依赖以及现代版 Node.js
RUN apt-get update && apt-get install -y curl git unzip \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs

# 引入 Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

# 极速安装后端依赖
RUN composer install --no-dev --optimize-autoloader

# 极速安装前端依赖并打包
RUN npm ci
RUN npm run build


# ==========================================
# 阶段 2: 生产运行环境 (ARM64 组装阶段)
# ==========================================
# 这个阶段会自动继承 GitHub Action 传递过来的 linux/arm64 架构
FROM dunglas/frankenphp:php8.4 AS runner

ENV APP_ENV=production
ENV APP_DEBUG=false

# 安装所需的 PHP 扩展
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    redis \
    pcntl \
    opcache \
    zip

WORKDIR /app

# 从 builder 阶段复制已经编译好的完整项目
COPY --from=builder /app /app

# 确保 Laravel 目录权限正确
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

# 暴露端口
EXPOSE 8000

# 启动命令：自动清理重建缓存并启动 Octane
CMD ["sh", "-c", "php artisan optimize:clear && php artisan optimize && php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8000"]
