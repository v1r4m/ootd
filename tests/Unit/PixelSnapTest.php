<?php

namespace Tests\Unit;

use App\Services\PixelSnap;
use PHPUnit\Framework\TestCase;

class PixelSnapTest extends TestCase
{
    /** 흰 배경 1024² 위에 "흐릿한 도트풍" 캐릭터(빨간 몸통 + 검은 외곽선 + 노이즈)를 그린다. */
    private function fakeModelOutput(): string
    {
        $img = imagecreatetruecolor(1024, 1024);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        imagefilledrectangle($img, 400, 150, 620, 870, imagecolorallocate($img, 20, 20, 20));
        imagefilledrectangle($img, 410, 160, 610, 860, imagecolorallocate($img, 220, 50, 50));
        mt_srand(1);
        for ($i = 0; $i < 5000; $i++) { // 안티앨리어싱/노이즈 흉내
            $v = mt_rand(180, 255);
            imagesetpixel($img, mt_rand(410, 610), mt_rand(160, 860), imagecolorallocate($img, $v, mt_rand(30, 70), 50));
        }
        ob_start();
        imagepng($img);

        return ob_get_clean();
    }

    public function test_output_is_grid_aligned_with_limited_palette(): void
    {
        $snap = new PixelSnap(spriteHeight: 80, grid: 128, scale: 8, colors: 16);
        $out = imagecreatefromstring($snap->process($this->fakeModelOutput()));

        $this->assertSame(1024, imagesx($out));
        $this->assertSame(1024, imagesy($out));

        // 8x8 블록 안은 전부 같은 색이어야 한다(격자 정렬).
        $colors = [];
        for ($by = 0; $by < 128; $by++) {
            for ($bx = 0; $bx < 128; $bx++) {
                $c = imagecolorat($out, $bx * 8, $by * 8);
                $this->assertSame($c, imagecolorat($out, $bx * 8 + 7, $by * 8 + 7));
                $colors[$c] = true;
            }
        }
        $this->assertLessThanOrEqual(17, count($colors)); // 팔레트 16 + 배경 흰색

        // 캐릭터 키 ≈ 80 도트, 가운데 정렬
        $rows = 0;
        for ($by = 0; $by < 128; $by++) {
            for ($bx = 0; $bx < 128; $bx++) {
                if (imagecolorat($out, $bx * 8, $by * 8) !== 0xFFFFFF) { $rows++; break; }
            }
        }
        $this->assertEqualsWithDelta(80, $rows, 1);
        $this->assertSame(0xFFFFFF, imagecolorat($out, 0, 0));
    }

    public function test_blank_image_is_returned_untouched(): void
    {
        $img = imagecreatetruecolor(64, 64);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        ob_start();
        imagepng($img);
        $png = ob_get_clean();

        $this->assertSame($png, (new PixelSnap)->process($png));
    }
}
