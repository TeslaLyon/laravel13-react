<?php

namespace App\Console\Commands;

use App\Services\Crawler\TpdbApiService;
use Illuminate\Console\Command;

class TestTpdbTokenCommand extends Command
{
    protected $signature = 'tpdb:test {actor? : 测试查询的演员名称，默认为 Angela White}';
    protected $description = '测试 ThePornDB API Token 连通性并验证演员信息及性别解析';

    public function handle(TpdbApiService $tpdbService): int
    {
        $this->info("🔍 开始测试 ThePornDB API 连接与 Token 有效性...");

        $token = config('services.tpdb.api_token') ?: env('TPDB_API_TOKEN');
        if (empty($token)) {
            $this->error("❌ 未检测到 TPDB_API_TOKEN！请先在 .env 中设置 TPDB_API_TOKEN=your_token");
            return 1;
        }

        $maskedToken = substr($token, 0, 8) . '...' . substr($token, -4);
        $this->line("🔑 当前配置的 Token: {$maskedToken}");

        $testRes = $tpdbService->testConnection();
        if (!$testRes['success']) {
            $this->error("❌ 连接失败: " . $testRes['message']);
            return 1;
        }

        $this->info("✅ {$testRes['message']}");

        $actorName = $this->argument('actor') ?: 'Angela White';
        $this->newLine();
        $this->info("🔎 正在检索演员: [{$actorName}] ...");

        $genderInt = $tpdbService->resolveGender($actorName);
        $genderText = match ($genderInt) {
            TpdbApiService::GENDER_MALE   => '1 - 男 (Male)',
            TpdbApiService::GENDER_FEMALE => '2 - 女 (Female)',
            TpdbApiService::GENDER_TRANS  => '3 - 变性人 (Transgender)',
            default                       => '0 - 未知 (Unknown)',
        };

        $performer = $tpdbService->searchPerformer($actorName);

        if ($performer) {
            $this->table(
                ['属性', '值'],
                [
                    ['姓名 (Name)', $performer['name'] ?? '-'],
                    ['Slug', $performer['slug'] ?? '-'],
                    ['原 API 性别 (Gender)', $performer['extra']['gender'] ?? '未提供'],
                    ['系统解析性别', $genderText],
                    ['生日 (Birthday)', $performer['extra']['birthday'] ?? '-'],
                    ['罩杯 (Cupsize)', $performer['extra']['cupsize'] ?? '-'],
                    ['头像 (Image)', $performer['image'] ?? '-'],
                ]
            );
        } else {
            $this->warn("⚠️ API 未返回 [{$actorName}] 的详细信息，解析性别结果: {$genderText}");
        }

        return 0;
    }
}

