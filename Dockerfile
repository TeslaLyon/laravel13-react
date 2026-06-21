# ==========================================
# 阶段 1: 统一构建阶段 (基于 Debian 的 FrankenPHP)
# ==========================================
# 去掉 -alpine，默认即为基于 Debian Bookworm 的原生镜像
FROM dunglas/frankenphp:php8.4 AS builder

# 安装系统级依赖以及现代版 Node.js (22.x LTS)
# 这一步使用 NodeSource 官方源，确保你的 Vite 插件完美运行
RUN apt-get update && apt-get install -y curl git unzip \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs

# 引入 Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

# 安装后端依赖
RUN composer install --no-dev --optimize-autoloader

# 安装前端依赖并打包
RUN npm ci
RUN npm run build


# ==========================================
# 阶段 2: 生产运行环境 (纯净 Debian 版)
# ==========================================
FROM dunglas/frankenphp:php8.4 AS runner

ENV APP_ENV=production
ENV APP_DEBUG=false

# Debian 镜像同样内置了 install-php-extensions，极其方便
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

# 确保 Laravel 目录权限正确 (Debian 中 www-data 是内置的标准 Web 用户)
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

# 暴露端口
EXPOSE 8000

# 启动常驻内存的 Octane 进程
CMD ["sh", "-c", "php artisan optimize:clear && php artisan optimize && php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8000"]
