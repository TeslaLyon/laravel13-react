<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_group_user', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('user_group_id');
            $table->boolean('is_displayed')->default(true)->comment('是否在个人名片/帖子侧边栏展示该组横幅');

            $table->primary(['user_id', 'user_group_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('user_group_id')->references('id')->on('user_groups')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_group_user');
    }
};
