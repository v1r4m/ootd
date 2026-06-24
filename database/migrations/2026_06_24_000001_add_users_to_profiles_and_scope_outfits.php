<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            // 유저당 프로필 1개 → user_id 는 nullable + unique
            $table->foreignId('user_id')->nullable()->after('id')
                ->constrained()->cascadeOnDelete();
            $table->unique('user_id');
        });

        Schema::table('outfits', function (Blueprint $table) {
            // 전역 unique(worn_on) 는 단일 사용자 전제 → 프로필별로 스코프
            $table->dropUnique('outfits_worn_on_unique');
            $table->unique(['profile_id', 'worn_on']);
        });
    }

    public function down(): void
    {
        Schema::table('outfits', function (Blueprint $table) {
            $table->dropUnique(['profile_id', 'worn_on']);
            $table->unique('worn_on');
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
