<?php

namespace App\Console\Commands;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Console\Command;

class ClaimProfile extends Command
{
    protected $signature = 'profile:claim {email : 기존 옷장을 가져갈 계정 이메일}';

    protected $description = '소유자 없는(orphan) 기존 프로필을 지정한 계정에 귀속시킨다';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("이메일 {$this->argument('email')} 계정을 찾을 수 없어요. 먼저 회원가입 해주세요.");

            return self::FAILURE;
        }

        $orphan = Profile::whereNull('user_id')->first();

        if (! $orphan) {
            $this->error('소유자 없는 프로필이 없어요. (이미 귀속됐을 수 있어요)');

            return self::FAILURE;
        }

        // 가입 시 자동 생성된 빈 프로필이 있으면 비우고 orphan 을 가져온다 (유저당 1개 unique 보호)
        $existing = $user->profile;
        if ($existing && $existing->id !== $orphan->id) {
            if ($existing->outfits()->exists()) {
                $this->error('이 계정엔 이미 옷 기록이 있는 프로필이 있어요. 충돌 방지를 위해 수동 확인이 필요해요.');

                return self::FAILURE;
            }
            $this->info("빈 프로필 @{$existing->handle} 을(를) 정리하고 기존 프로필을 가져옵니다.");
            $existing->delete();
        }

        $orphan->update(['user_id' => $user->id]);

        $this->info("@{$orphan->handle} ({$orphan->outfits()->count()}개 기록) → {$user->email} 에 귀속 완료!");

        return self::SUCCESS;
    }
}
