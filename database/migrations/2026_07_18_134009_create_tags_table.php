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
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->default('');
            $table->string('name_zh')->unique()->default('');
            $table->string('slug')->unique()->default('');
            $table->string('description')->default('');
            $table->string('avatar')->default('')->comment('正方形 avatar');
            $table->string('avatar_vertical')->default('')->comment('竖形 avatar');
            $table->string('avatar_horizontal')->default('')->comment('横形 avatar');
            $table->unsignedInteger('video_num')->default(0);
            $table->unsignedInteger('follow_num')->default(0);
            $table->unsignedInteger('photo_num')->default(0);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
