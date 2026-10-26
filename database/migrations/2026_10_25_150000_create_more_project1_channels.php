<?php

use App\Models\Channel;
use App\Services\Crawler\Project1VideoCrawlerService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * 新增的 4 个 Project1 体系片商 (Squirted, True Amateurs, Next Door Hobby, Milfed)
     */
    protected const CHANNELS = [
        'squirted',
        'trueamateurs',
        'nextdoorhobby',
        'milfed',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $crawlerService = app(Project1VideoCrawlerService::class);
        foreach (self::CHANNELS as $slug) {
            $crawlerService->ensureChannel($slug);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Channel::whereIn('slug', self::CHANNELS)->delete();
    }
};

