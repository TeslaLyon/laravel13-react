#!/bin/sh
# 遇到任何错误立即停止脚本执行
set -e

echo "🚀 [Initialization] Starting Laravel Storage & Cache bootstrap..."

# 1. 确保运行时必要的目录结构完整存在
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/app
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs

# 2. 清理可能残余的旧缓存
rm -f bootstrap/cache/*.php

# 3. 在容器启动时（拿到真实的生产环境 env 后）生成全新的路由、配置全量缓存
php artisan optimize
php artisan view:cache

# 4. 🔑 核心权限校准：
# 将刚刚由 root 权限编译生成的缓存文件，强行重置所有权给 www-data
chown -R www-data:www-data /app/storage /app/bootstrap/cache
chmod -R 775 /app/storage /app/bootstrap/cache

echo "✅ [Initialization] Bootstrapping completed successfully."
echo "⚡ [Runtime] Launching FrankenPHP via Octane..."

# 5. ⚠️ 使用 exec "$@" 替换当前 shell 进程（保持 PID 1）
# 确保集群执行 stop 或滚动更新时，容器能实现 HTTP 请求无损平滑下线（Zero-downtime）
exec "$@"
