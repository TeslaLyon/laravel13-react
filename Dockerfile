# ==========================================
# 阶段 1: 依赖与前端构建阶段 (Builder)
# ==========================================
# 保持 $BUILDPLATFORM，让构建工作在 Runner 宿主原生架构（x86_64）高速执行
FROM --platform=$BUILDPLATFORM dunglas/frankenphp:1.12.4-php8.4 AS builder

WORKDIR /app

# 安装构建依赖与 Node.js 22
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    unzip \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 💡 优化 1：挂载 Composer 下载缓存，避免缓存文件污染镜像层
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/root/.composer/cache \
    composer install --no-dev --optimize-autoloader --no-scripts --prefer-dist --ignore-platform-reqs

# 💡 优化 2：挂载 npm 缓存，提升依赖解析速度
COPY package.json package-lock.json ./
RUN --mount=type=cache,target=/root/.npm \
    npm install --legacy-peer-deps

# 复制源码并编译前端产物 (wayfinder 此时能正常调用上文就绪的 php 与 vendor)
COPY . .
# 传入前端 CDN URL，确保 Vite 构建阶段生成带 CDN 前缀的资源地址 fallback
ARG VITE_CDN_URL
ENV VITE_CDN_URL=$VITE_CDN_URL
RUN npm run build


# ==========================================
# 阶段 2: 生产运行环境 (Runner - 指定 target 架构 ARM64)
# ==========================================
FROM dunglas/frankenphp:1.12.4-php8.4 AS runner

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    webp \
    && rm -rf /var/lib/apt/lists/*

ENV APP_ENV=production \
    APP_DEBUG=false \
    FRANKENPHP_USER=www-data \
    FRANKENPHP_GROUP=www-data

WORKDIR /app

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

COPY deployment/php/production.ini $PHP_INI_DIR/conf.d/99-laravel-production.ini

# 🚀 核心优化 3：先拷源码（通过 .dockerignore 排除 node_modules/.git 等冗余）
COPY --chown=www-data:www-data . .

# 🚀 核心优化 4：精准拷入生产 vendor 与前端 public/build 编译产物，彻底丢弃 node_modules！
COPY --from=builder --chown=www-data:www-data /app/vendor ./vendor
COPY --from=builder --chown=www-data:www-data /app/public/build ./public/build

# 💡 优化 5：使用 --chmod=755 合并指令，减少 1 层只读镜像层
COPY --chmod=755 docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

EXPOSE 8000

HEALTHCHECK --interval=10s --timeout=5s --start-period=15s --retries=3 \
    CMD curl -f http://127.0.0.1:8000/up || exit 1

ENTRYPOINT ["docker-entrypoint.sh"]

CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8000", "--workers=8"]
