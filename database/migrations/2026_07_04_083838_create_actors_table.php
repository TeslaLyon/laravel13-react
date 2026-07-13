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
        Schema::create('actors', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable(false);
            $table->string('slug')->nullable(false);
            $table->unsignedInteger('original_id')->default(0)->comment('原始 id,用来区别重名的演员');
            $table->string('avatar')->default('')->comment('头像（正方形）');
            $table->text('booty_img')->nullable()->comment('列表图（长方形9:16）');
            $table->string('banner')->default('')->comment('详情页 banner（长方形）');
            $table->unsignedTinyInteger('gender')->default(0)->comment('性别：[0-未知；1-男；2-女；3-变性人]');
            $table->boolean('is_trans_model')->default(false)->comment('是否为变性人');
            $table->unsignedInteger('follow_num')->default(0)->comment('关注数');
            $table->unsignedInteger('view_num')->default(0)->comment('浏览数');
            $table->timestamp('last_add_at')->nullable()->comment('最后添加作品时间');
            $table->softDeletes();
            $table->timestamps();

            $table->index('name');
            $table->index('slug');
            $table->index(['name', 'deleted_at'], 'actors_name_deleted_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('actors');
    }
};
