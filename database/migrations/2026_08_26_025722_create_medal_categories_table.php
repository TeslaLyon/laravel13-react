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
        Schema::create('medal_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->comment('分类名称，如：里程碑、创作者、特殊活动');
            $table->string('slug', 64)->unique()->comment('分类标识');
            $table->string('description', 255)->nullable()->comment('分类简述');
            $table->unsignedInteger('display_order')->default(0)->comment('排序权重');
            $table->boolean('is_active')->default(true)->comment('是否启用');
            $table->timestamps();

            $table->index(['is_active', 'display_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medal_categories');
    }
};
