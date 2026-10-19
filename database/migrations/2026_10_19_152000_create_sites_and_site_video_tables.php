<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->string('name')->comment('站点/系列名称，例如 BangBros 18, ZZ Series, Brazzers Exxtra');
            $table->string('slug')->comment('站点/系列slug，例如 bangbros-18, zz-series');
            $table->string('short_name')->nullable()->comment('短代码标识，例如 bbe, bb');
            $table->unsignedBigInteger('original_id')->nullable()->comment('Project1远端 collection ID');
            $table->string('custom_url')->nullable()->comment('官方自定义页面路由');
            $table->string('avatar')->default('')->comment('站点图标/Logo');
            $table->unsignedInteger('video_num')->default(0)->comment('下辖视频总数统计');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态：1正常 0停用');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['channel_id', 'slug']);
            $table->index('channel_id');
            $table->index('slug');
        });

        Schema::create('site_video', function (Blueprint $table) {
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();

            $table->primary(['video_id', 'site_id']);
            $table->index('site_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_video');
        Schema::dropIfExists('sites');
    }
};

