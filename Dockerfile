# ==========================================
# 阶段 1: 依赖构建阶段 (Builder)
# ==========================================
FROM --platform=$BUILDPLATFORM dunglas/frankenphp:1.12.4-php8.4 AS builder

WORKDIR /app

# 安装基础构建依赖
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# 引入官方最新版 Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 复制依赖定义文件（充分利用 Docker 缓存层）
COPY composer.json composer.lock ./

# 仅安装生产环境依赖
RUN composer install --no-dev --optimize-autoloader --no-scripts --prefer-dist

# 复制项目全量代码
COPY . .

# ==========================================
# 阶段 2: 生产运行环境 (Runner)
# ==========================================
FROM dunglas/frankenphp:1.12.4-php8.4 AS runner

# 设置生产环境核心环境变量
ENV APP_ENV=production \
    APP_DEBUG=false \
    # 💡 官方规范：指示 FrankenPHP 将工作进程降权至 www-data 用户组运行，避免 root 污染
    FRANKENPHP_USER=www-data \
    FRANKENPHP_GROUP=www-data

WORKDIR /app

# 安装高性能 Laravel 13 所需的 PHP 核心扩展
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    redis \
    pcntl \
    opcache \
    zip

# 复制自定义的生产环境 PHP 优化配置
COPY deployment/php/production.ini $PHP_INI_DIR/conf.d/99-laravel-production.ini

# 从 Builder 阶段复制干净的代码库，并直接把所有权赋给 www-data
COPY --from=builder --chown=www-data:www-data /app /app

# 复制并配置容器入口脚本
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# 暴露 HTTP 标准端口
EXPOSE 8000

# 💡 核心修复：显式重写健康检查，让 Docker 去戳 8000 端口的 /up 路由
# interval: 检查间隔, timeout: 超时, start-period: 启动缓冲时间（给 Laravel 留足生成缓存的时间）, retries: 失败重试次数
HEALTHCHECK --interval=10s --timeout=5s --start-period=15s --retries=3 \
  CMD curl -f http://localhost:8000/up || exit 1

# 声明运行时入口
ENTRYPOINT ["docker-entrypoint.sh"]

# 使用数组格式的 CMD，便于 PID 1 信号传递（实现无损优雅停机）
# 🚀 升级为 8 个常驻并发 Worker，榨干 4 核 CPU 的算力
CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8000", "--workers=8"]
