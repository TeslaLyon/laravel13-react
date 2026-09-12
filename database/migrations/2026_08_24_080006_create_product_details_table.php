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
        Schema::create('product_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();

            $table->json('specs')->nullable()->comment('详细技术参数字典');
            $table->longText('content')->nullable()->comment('图文/Markdown详情介绍');

            // 交付发货资产 (严格受限访问)
            $table->string('delivery_type', 30)->default('netdisk')->comment('交付方式: netdisk, card_key, download');
            $table->json('delivery_content')->nullable()->comment('真实发货内容(网盘地址/解压密码/卡密)');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_details');
    }
};
