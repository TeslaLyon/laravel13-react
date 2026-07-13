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
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('片商名称');
            $table->string('slug')->comment('片商slug');
            $table->unsignedTinyInteger('data_crawl_type')->default(0);
            $table->unsignedInteger('video_num')->default(0);
            $table->unsignedInteger('follow_num')->default(0);
            $table->string('avatar')->default('')->comment('片商(小)头像');
            $table->string('logo')->default('')->comment('片商(大)logo');
            $table->string('official_website_url')->default('');
            $table->timestamps();
            $table->softDeletes();
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
