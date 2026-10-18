<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('actor_details')) {
            Schema::table('actor_details', function (Blueprint $table) {
                if (!Schema::hasColumn('actor_details', 'basic_info')) {
                    $table->jsonb('basic_info')->default('{}')->comment('基础信息');
                }
                if (!Schema::hasColumn('actor_details', 'physical_info')) {
                    $table->jsonb('physical_info')->default('{}')->comment('外形数据');
                }
                if (!Schema::hasColumn('actor_details', 'socials')) {
                    $table->jsonb('socials')->default('{}')->comment('社交媒体');
                }
                if (!Schema::hasColumn('actor_details', 'gallery')) {
                    $table->text('gallery')->nullable()->comment('图册');
                }
            });

            // 如果存在旧版本平铺列，平滑迁移数据至 jsonb 结构
            try {
                if (Schema::hasColumn('actor_details', 'birthday') && Schema::hasColumn('actor_details', 'basic_info')) {
                    $records = DB::table('actor_details')->get();
                    foreach ($records as $record) {
                        $basic = json_decode($record->basic_info ?? '{}', true) ?: [];
                        $physical = json_decode($record->physical_info ?? '{}', true) ?: [];
                        $socials = json_decode($record->socials ?? '{}', true) ?: [];

                        if (!empty($record->birthday)) $basic['birthday'] = $record->birthday;
                        if (!empty($record->birthplace)) $basic['birthplace'] = $record->birthplace;
                        if (!empty($record->astrology)) $basic['astrology'] = $record->astrology;
                        if (!empty($record->nationality)) $basic['nationality'] = $record->nationality;

                        if (!empty($record->ethnicity)) $physical['ethnicity'] = $record->ethnicity;
                        if (!empty($record->hair_color)) $physical['hair_colour'] = $record->hair_color;
                        if (!empty($record->height)) $physical['height'] = $record->height;
                        if (!empty($record->weight)) $physical['weight'] = $record->weight;
                        if (!empty($record->measurements)) $physical['measurements'] = $record->measurements;
                        if (!empty($record->tattoos)) $physical['tattoos'] = $record->tattoos;
                        if (!empty($record->piercings)) $physical['piercings'] = $record->piercings;

                        if (!empty($record->social_media_link)) {
                            $decodedSocials = json_decode($record->social_media_link, true);
                            if (is_array($decodedSocials)) {
                                $socials = array_merge($socials, $decodedSocials);
                            }
                        }

                        DB::table('actor_details')->where('id', $record->id)->update([
                            'basic_info'    => json_encode($basic),
                            'physical_info' => json_encode($physical),
                            'socials'       => json_encode($socials),
                        ]);
                    }
                }
            } catch (\Throwable) {
                // 忽略非致命旧数据迁移异常
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 保持数据安全，不回滚删除列
    }
};

