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
            $table->unsignedBigInteger('actor_id');
            $table->string('astrology', 20)->default('')->comment('星座');
            $table->string('birthday', 5)->default('')->comment('生日，格式:12-31');
            $table->unsignedSmallInteger('born')->default(0)->comment('出生的年份，格式：2000');
            $table->string('birthplace')->default('')->comment('出生地');
            $table->string('ethnicity')->default('')->comment('种族');
            $table->text('gallery')->nullable()->comment('图册');
            $table->string('hair_color')->default('')->comment('头发颜色');
            $table->string('height')->default('')->comment('身高');
            $table->string('weight')->default('')->comment('体重');
            $table->string('measurements')->default('')->comment('三围');
            $table->string('nationality')->default('')->comment('国籍');
            $table->string('piercings')->default('')->comment('穿洞位置，例如：耳洞、肚脐');
            $table->text('social_media_link')->nullable()->comment('各个社交媒体链接');
            $table->string('tattoos')->default('')->comment('纹身信息');
            $table->timestamps();
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
