# ==========================================
# 阶段 1: 统一构建阶段 (PHP + Node.js 混合环境)
# ==========================================
# 我们直接使用带有 PHP 的镜像作为构建基础
FROM dunglas/frankenphp:php8.5-alpine AS builder

# 关键修复：在 PHP 环境中安装 Node.js 和 npm
RUN apk add --no-cache nodejs npm

# 引入 Composer 工具
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
# 复制所有代码到容器
COPY . .

# 步骤 A：先安装 PHP 核心依赖
# 这一步会生成 vendor 目录，保证后续的 php artisan 命令可以正常运行
RUN composer install --no-dev --optimize-autoloader

# 步骤 B：安装前端依赖并执行打包
# 现在环境中既有 node/npm，又有完整的 PHP 和 vendor，Vite 插件可以完美运行了！
RUN npm ci
RUN npm run build


# ==========================================
# 阶段 2: 生产运行环境 (纯净的 FrankenPHP + Octane)
# ==========================================
FROM dunglas/frankenphp:php8.5-alpine AS runner

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

# 从 builder 阶段把【已经编译好】的完整项目复制过来
# 里面已经包含了 vendor 目录和 public/build 前端静态文件
COPY --from=builder /app /app

# 确保 Laravel 目录权限正确
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

# 暴露 8000 端口
EXPOSE 8000

# 启动命令
CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8000"]
