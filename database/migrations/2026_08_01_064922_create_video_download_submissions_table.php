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
        Schema::create('video_download_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('video_id')->constrained()->onDelete('cascade');

            // 提交类型：torrent (种子文件), magnet (磁力链接), link (第三方网盘/下载链接)
            $table->string('type')->comment('提交类型：torrent (种子文件), magnet (磁力链接), link (第三方网盘/下载链接)');

            // 储存链接内容、网盘地址或种子文件相对路径
            $table->text('content');

            $table->string('extraction_code', 50)->nullable()->comment('网盘提取码');
            $table->string('archive_password', 100)->nullable()->comment('文件解压密码');
            $table->text('remark')->nullable()->comment('用户提交备注');

            // 审核状态：0-待审核, 1-已通过并颁发奖励, 2-已驳回
            $table->tinyInteger('status')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_download_submissions');
    }
};
