<?php

namespace App\Console\Commands;

use App\Models\Profile;
use App\Services\AvatarGenerator;
use App\Services\PixelSnap;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SetReferenceAvatar extends Command
{
    protected $signature = 'avatar:reference
        {handle : 프로필 handle (예: viram)}
        {image : 기준 아바타 이미지 경로 (PNG/JPG)}
        {--style=default : 헤어스타일 종류 (default|bun|ponytail|down). default 는 헤어 언급이 없을 때 쓴다}';

    protected $description = '프로필의 기준 아바타를 지정한다. 이후 아바타는 이 이미지의 얼굴·화풍을 유지한 채 옷만 바꿔 생성된다. '
        .'옷·소품이 단순한 이미지일수록 좋다(기준 이미지의 소품이 결과에 따라 나오기 쉽다)';

    /** fal 에 매번 보내는 크기. 입력 이미지도 MP 단위 과금이라 작게 보낸다. */
    private const SIZE = 512;

    public function handle(): int
    {
        $profile = Profile::where('handle', $this->argument('handle'))->first();
        if (! $profile) {
            $this->error("handle '{$this->argument('handle')}' 프로필이 없어요.");

            return self::FAILURE;
        }

        $style = $this->option('style');
        if (! in_array($style, AvatarGenerator::REFERENCE_STYLES, true)) {
            $this->error('style 은 '.implode('|', AvatarGenerator::REFERENCE_STYLES).' 중 하나여야 해요.');

            return self::FAILURE;
        }

        $src = @imagecreatefromstring((string) @file_get_contents($this->argument('image')));
        if (! $src) {
            $this->error('이미지를 읽을 수 없어요: '.$this->argument('image'));

            return self::FAILURE;
        }

        // 정사각형으로 맞춘 뒤(흰 여백) 512px 로. 도트 경계를 지키려고 nearest-neighbor.
        $side = max(imagesx($src), imagesy($src));
        $square = imagecreatetruecolor($side, $side);
        imagefill($square, 0, 0, imagecolorallocate($square, 255, 255, 255));
        imagecopy($square, $src, intdiv($side - imagesx($src), 2), intdiv($side - imagesy($src), 2), 0, 0, imagesx($src), imagesy($src));
        $small = imagescale($square, self::SIZE, self::SIZE, IMG_NEAREST_NEIGHBOUR);

        ob_start();
        imagepng($small, null, 9);
        $png = (new PixelSnap)->clean(ob_get_clean()); // 배경 얼룩·그림자 정리
        $path = AvatarGenerator::referencePath($profile, $style);
        Storage::disk('local')->put($path, $png);

        $this->info("기준 아바타 저장: storage/app/private/{$path} (".self::SIZE.'px)');

        return self::SUCCESS;
    }
}
