# ==========================================
# 阶段 1: 前端构建 (Node.js 编译 React/Vite 资产)
# ==========================================
FROM node:24-alpine AS frontend

WORKDIR /app

# 复制前端依赖配置并安装
COPY package.json package-lock.json ./
RUN npm ci

# 复制所有项目文件并执行生产环境构建
COPY . .
RUN npm run build


# ==========================================
# 阶段 2: 生产运行环境 (FrankenPHP + Octane)
# ==========================================
FROM dunglas/frankenphp:php8.3-alpine AS runner

# 设置生产环境标识
ENV APP_ENV=production
ENV APP_DEBUG=false

# 安装所需的 PHP 扩展 (PostgreSQL 和 Redis/Valkey 必备)
# FrankenPHP 镜像内置了 install-php-extensions 脚本，非常方便
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    redis \
    pcntl \
    opcache \
    zip

# 将工作目录设置为 /app
WORKDIR /app

# 复制整个项目到容器中
COPY . /app

# 关键步：从阶段 1 (frontend) 中把编译好的前端静态文件复制过来
COPY --from=frontend /app/public/build /app/public/build

# 安装 Composer (仅安装生产环境依赖)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader

# 确保 Laravel 的存储和缓存目录有正确的读写权限
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

# 暴露 8000 端口给 Caddy 网关使用
EXPOSE 8000

# 容器启动时运行 Octane 命令
CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8000"]
