<?php

use App\Models\Channel;
use App\Services\Crawler\Project1VideoCrawlerService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * 新增的 Project1 体系片商（原 AdultMobile / Mile High Media 体系站点）
     */
    protected const CHANNELS = [
        'sweetheartvideo',
        'sweetsinner',
        'realityjunkies',
        'doghousedigital',
        'familysinners',
        'sexonsight',
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

