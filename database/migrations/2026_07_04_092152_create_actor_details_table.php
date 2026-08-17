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
        Schema::create('actor_details', function (Blueprint $table) {
            $table->id();

            // 关联 actors 表的 ID (注意这里的类型要与你 actors 表的主键类型一致)
            // 你之前提到你目前的主键是 $table->id()，即 unsignedBigInteger
            $table->unsignedBigInteger('actor_id')->unique()->comment('关联演员主表');

            // 存放你的 jsonb 数据
            $table->jsonb('basic_info')->default('{}')->comment('基础信息');
            $table->jsonb('physical_info')->default('{}')->comment('外形数据');
            $table->jsonb('socials')->default('{}')->comment('社交媒体');
            $table->text('gallery')->nullable()->comment('图册');

            $table->timestamps();

            // 建立外键约束，当主表演员被删除时，详情页数据一并删除 (级联删除)
            $table->foreign('actor_id')->references('id')->on('actors')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('actor_details');
    }
};
