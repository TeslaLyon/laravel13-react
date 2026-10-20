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
        Schema::table('feedback', function (Blueprint $table) {
            $table->text('admin_reply')->nullable()->after('content')->comment('管理员处理答复或处理说明');
            $table->foreignId('handled_by')->nullable()->after('status')->constrained('users')->nullOnDelete()->comment('处理管理员 ID');
            $table->timestamp('handled_at')->nullable()->after('handled_by')->comment('处理时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            $table->dropForeign(['handled_by']);
            $table->dropColumn(['admin_reply', 'handled_by', 'handled_at']);
        });
    }
};

