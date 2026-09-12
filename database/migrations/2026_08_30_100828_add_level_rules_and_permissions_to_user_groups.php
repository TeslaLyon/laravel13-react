<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_groups', function (Blueprint $table) {
            // 等级与积分阶梯
            if (!Schema::hasColumn('user_groups', 'level')) {
                $table->unsignedSmallInteger('level')->default(1)->after('title')->comment('等级数字（如 1, 2, 3...）');
            }
            if (!Schema::hasColumn('user_groups', 'is_credit_based')) {
                $table->boolean('is_credit_based')->default(true)->after('level')->index()->comment('是否为积分晋升组');
            }
            if (!Schema::hasColumn('user_groups', 'credits_min')) {
                $table->decimal('credits_min', 12, 2)->default(0.00)->after('is_credit_based')->index()->comment('晋升所需最低总积分');
            }
            if (!Schema::hasColumn('user_groups', 'credits_max')) {
                $table->decimal('credits_max', 12, 2)->nullable()->after('credits_min')->comment('积分上限（null 代表无上限）');
            }

            // 基础权限控制开关
            if (!Schema::hasColumn('user_groups', 'read_permission_level')) {
                $table->unsignedSmallInteger('read_permission_level')->default(10)->after('credits_max')->comment('阅读权限级别（值越大权限越高）');
            }
            if (!Schema::hasColumn('user_groups', 'allow_search')) {
                $table->boolean('allow_search')->default(true)->after('read_permission_level')->comment('是否允许使用站内搜索');
            }
            if (!Schema::hasColumn('user_groups', 'allow_custom_title')) {
                $table->boolean('allow_custom_title')->default(false)->after('allow_search')->comment('是否允许自定义个性头衔');
            }
            if (!Schema::hasColumn('user_groups', 'allow_upload_attachment')) {
                $table->boolean('allow_upload_attachment')->default(false)->after('allow_custom_title')->comment('是否允许上传附件');
            }
            if (!Schema::hasColumn('user_groups', 'daily_post_limit')) {
                $table->unsignedSmallInteger('daily_post_limit')->default(50)->after('allow_upload_attachment')->comment('每日发帖/回帖上限次数');
            }

            // 预留细粒度权限配置字典
            if (!Schema::hasColumn('user_groups', 'extra_permissions')) {
                $table->json('extra_permissions')->nullable()->after('daily_post_limit')->comment('扩展权限 JSON 配置');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_groups', function (Blueprint $table) {
            $table->dropColumn([
                'level',
                'is_credit_based',
                'credits_min',
                'credits_max',
                'read_permission_level',
                'allow_search',
                'allow_custom_title',
                'allow_upload_attachment',
                'daily_post_limit',
                'extra_permissions',
            ]);
        });
    }
};
