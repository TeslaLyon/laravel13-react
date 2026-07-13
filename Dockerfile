# ==========================================
# 阶段 1: 依赖构建阶段 (Builder)
# ==========================================
FROM --platform=$BUILDPLATFORM dunglas/frankenphp:1.12.4-php8.4 AS builder

WORKDIR /app

# 💡 核心修复：补回系统构建依赖以及现代版 Node.js 22
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    unzip \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# 引入官方最新版 Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 🚀 极致缓存优化：先单独复制依赖定义文件，让 GitHub 能够秒级缓存 node_modules 和 vendor
# COPY composer.json composer.lock package.json package-lock.json ./

# # 极速安装后端与前端依赖
# RUN composer install --no-dev --optimize-autoloader --no-scripts --prefer-dist --ignore-platform-reqs \
#     && npm ci

# 🚀 极致缓存优化：拆分后端与前端，最大限度利用 Docker 缓存
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --prefer-dist --ignore-platform-reqs

COPY package.json package-lock.json ./
# 将严格的 ci 换成 install，增加容错率，并忽略严格的 peer 依赖冲突
RUN npm install --legacy-peer-deps

# 复制项目全量源码
COPY . .

# 🔥 核心修复：全量编译前端资产，在容器内堂堂正正生成 public/build 目录！
RUN npm run build


# ==========================================
# 阶段 2: 生产运行 environment (Runner)
# ==========================================
FROM dunglas/frankenphp:1.12.4-php8.4 AS runner

# 安装生产环境所需的极简监控工具 curl
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    && rm -rf /var/lib/apt/lists/*

# 设置生产环境核心环境变量
ENV APP_ENV=production \
    APP_DEBUG=false \
    FRANKENPHP_USER=www-data \
    FRANKENPHP_GROUP=www-data

WORKDIR /app

# 安装高性能 Laravel 13 所需的 PHP 核心扩展
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    redis \
    pcntl \
    bcmath \
    opcache \
    zip \
    gd \
    exif \
    intl \
    igbinary

# 复制自定义的生产环境 PHP 优化配置
COPY deployment/php/production.ini $PHP_INI_DIR/conf.d/99-laravel-production.ini

# 从 Builder 阶段复制干净的代码库（此时完美包含了刚刚编译好的 public/build）
COPY --from=builder --chown=www-data:www-data /app /app

# 复制并配置容器入口脚本
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# 暴露 HTTP 标准端口
EXPOSE 8000

# 显式重写健康检查，精准锁定 8000 端口
HEALTHCHECK --interval=10s --timeout=5s --start-period=15s --retries=3 \
  CMD curl -f http://127.0.0.1:8000/up || exit 1

# 声明运行时入口
ENTRYPOINT ["docker-entrypoint.sh"]

# 启动命令：使用 8 个常驻并发 Worker 榨干 4 核 CPU
CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8000", "--workers=8"]
