<?php

use App\Models\Channel;
use App\Services\Crawler\Project1VideoCrawlerService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $crawlerService = app(Project1VideoCrawlerService::class);
        $crawlerService->ensureChannel('sexyhub');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Channel::where('slug', 'sexyhub')->delete();
    }
};

