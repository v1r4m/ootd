<?php

namespace App\Services;

use GdImage;
use RuntimeException;

/**
 * 이미지 모델이 그린 "도트풍 그림"을 격자가 딱 맞는 진짜 스프라이트로 정규화한다.
 *
 * 1. 흰 배경을 뺀 캐릭터 영역(bbox)을 찾는다.
 * 2. 캐릭터 높이가 spriteHeight 픽셀이 되도록, 셀마다 "가장 많이 나온 색"으로 다운샘플한다.
 *    (평균을 내면 외곽선이 회색으로 번지므로 최빈값을 쓴다.)
 * 3. 팔레트를 colors 색으로 줄인다. (디더링 없음)
 * 4. grid x grid 정사각 캔버스 가운데에 놓고, nearest-neighbor 로 scale 배 확대한다.
 *
 * API 호출 없이 GD 만 쓰므로 비용은 0원.
 */
class PixelSnap
{
    /** 이 값보다 밝은(RGB 모두) 픽셀은 배경으로 본다. */
    private const BG_THRESHOLD = 235;

    /** 최빈값을 셀 때 채널당 이 비트만 남겨 비슷한 색을 묶는다. */
    private const BUCKET_SHIFT = 4;

    /** 밝기(0~255)가 이 값보다 낮으면 외곽선 후보로 본다. */
    private const DARK_LUMA = 105;

    /** 셀의 캐릭터 픽셀 중 이 비율 이상이 어두우면 어두운 색을 우선한다. */
    private const DARK_SHARE = 0.2;

    /** 가장 큰 덩어리 대비 이 비율보다 작은 떨어진 조각은 지운다. */
    private const SPECK_RATIO = 0.02;

    public function __construct(
        private int $spriteHeight = 100,
        private int $grid = 128,
        private int $scale = 8,
        private int $colors = 24,
    ) {
    }

    public static function fromConfig(): self
    {
        $c = config('services.pixel');

        return new self(
            spriteHeight: (int) ($c['sprite_height'] ?? 100),
            grid: (int) ($c['grid'] ?? 128),
            scale: (int) ($c['scale'] ?? 8),
            colors: (int) ($c['colors'] ?? 24),
        );
    }

    public function process(string $png): string
    {
        $src = @imagecreatefromstring($png);
        if (! $src) {
            throw new RuntimeException('PixelSnap: 이미지를 읽을 수 없습니다.');
        }
        imagepalettetotruecolor($src);
        $this->clearOuterShadow($src);
        $this->removeSpecks($src);

        $bbox = $this->findBoundingBox($src);
        if ($bbox === null) {
            return $png; // 캐릭터가 없으면(전부 흰색) 손대지 않는다.
        }

        $small = $this->downsample($src, ...$bbox);
        $this->quantize($small);

        $canvas = $this->placeOnCanvas($small);
        $out = imagescale($canvas, $this->grid * $this->scale, $this->grid * $this->scale, IMG_NEAREST_NEIGHBOUR);

        ob_start();
        imagepng($out, null, 9);

        return ob_get_clean();
    }

    /**
     * 바깥 배경과 이어진 "밝은 무채색" 영역(바닥 그림자, 회색 배경 얼룩)을 흰색으로 지운다.
     * 이미지 테두리에서 flood fill 하므로, 외곽선 안쪽의 회색 옷은 건드리지 않는다.
     * (schnell 은 "no shadow" 지시를 잘 무시해서 후처리로 처리)
     */
    private function clearOuterShadow(GdImage $img): void
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $seen = new \SplFixedArray($w * $h);
        $stack = [];

        for ($x = 0; $x < $w; $x++) {
            $stack[] = $x;
            $stack[] = ($h - 1) * $w + $x;
        }
        for ($y = 0; $y < $h; $y++) {
            $stack[] = $y * $w;
            $stack[] = $y * $w + $w - 1;
        }

        $white = imagecolorallocate($img, 255, 255, 255);
        while ($stack) {
            $i = array_pop($stack);
            if ($seen[$i]) {
                continue;
            }
            $seen[$i] = true;
            $x = $i % $w;
            $y = intdiv($i, $w);
            if (! $this->isShadowLike(imagecolorat($img, $x, $y))) {
                continue;
            }
            imagesetpixel($img, $x, $y, $white);
            if ($x > 0) $stack[] = $i - 1;
            if ($x < $w - 1) $stack[] = $i + 1;
            if ($y > 0) $stack[] = $i - $w;
            if ($y < $h - 1) $stack[] = $i + $w;
        }
    }

    /**
     * 캐릭터 본체와 떨어진 작은 조각(반짝이 효과, 먼지 점)을 지운다.
     * 4px 블록 단위로 연결 요소를 찾고, 가장 큰 덩어리의 SPECK_RATIO 미만인 덩어리는 흰색으로 칠한다.
     */
    private function removeSpecks(GdImage $img): void
    {
        $b = 4;
        $w = imagesx($img);
        $h = imagesy($img);
        $cw = intdiv($w + $b - 1, $b);
        $ch = intdiv($h + $b - 1, $b);

        $solid = [];
        for ($cy = 0; $cy < $ch; $cy++) {
            for ($cx = 0; $cx < $cw; $cx++) {
                $hit = false;
                for ($y = $cy * $b; $y < min($h, $cy * $b + $b) && ! $hit; $y++) {
                    for ($x = $cx * $b; $x < min($w, $cx * $b + $b); $x++) {
                        if (! $this->isBackground(imagecolorat($img, $x, $y))) {
                            $hit = true;
                            break;
                        }
                    }
                }
                $solid[$cy * $cw + $cx] = $hit;
            }
        }

        $label = array_fill(0, $cw * $ch, 0);
        $sizes = [];
        $next = 0;
        foreach ($solid as $i => $on) {
            if (! $on || $label[$i]) {
                continue;
            }
            $next++;
            $sizes[$next] = 0;
            $stack = [$i];
            $label[$i] = $next;
            while ($stack) {
                $j = array_pop($stack);
                $sizes[$next]++;
                $x = $j % $cw;
                $y = intdiv($j, $cw);
                foreach ([[-1, 0], [1, 0], [0, -1], [0, 1]] as [$dx, $dy]) {
                    $nx = $x + $dx;
                    $ny = $y + $dy;
                    if ($nx < 0 || $ny < 0 || $nx >= $cw || $ny >= $ch) {
                        continue;
                    }
                    $k = $ny * $cw + $nx;
                    if ($solid[$k] && ! $label[$k]) {
                        $label[$k] = $next;
                        $stack[] = $k;
                    }
                }
            }
        }

        if ($next < 2) {
            return;
        }

        $min = max($sizes) * self::SPECK_RATIO;
        $white = imagecolorallocate($img, 255, 255, 255);
        foreach ($label as $i => $l) {
            if ($l && $sizes[$l] < $min) {
                $x = ($i % $cw) * $b;
                $y = intdiv($i, $cw) * $b;
                imagefilledrectangle($img, $x, $y, min($w, $x + $b) - 1, min($h, $y + $b) - 1, $white);
            }
        }
    }

    /** 배경이거나, 밝고(최대 채널 > 170) 채도가 낮은(채널 차 < 28) 색. */
    private function isShadowLike(int $rgb): bool
    {
        if ($this->isBackground($rgb)) {
            return true;
        }
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        $max = max($r, $g, $b);

        return $max > 170 && $max - min($r, $g, $b) < 28;
    }

    /**
     * 다운샘플 없이 배경만 정리한다(그림자·얼룩·떨어진 점 제거).
     * 참조 이미지 기반(edit) 출력처럼 이미 도트가 정돈된 이미지용 — 다시 격자화하면 오히려 뭉개진다.
     */
    public function clean(string $png): string
    {
        $img = @imagecreatefromstring($png);
        if (! $img) {
            throw new RuntimeException('PixelSnap: 이미지를 읽을 수 없습니다.');
        }
        imagepalettetotruecolor($img);
        $this->clearOuterShadow($img);
        $this->removeSpecks($img);

        ob_start();
        imagepng($img, null, 9);

        return ob_get_clean();
    }

    /** @return array{0:int,1:int,2:int,3:int}|null [x, y, w, h] */
    private function findBoundingBox(GdImage $img): ?array
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $minX = $w;
        $minY = $h;
        $maxX = -1;
        $maxY = -1;

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                if (! $this->isBackground(imagecolorat($img, $x, $y))) {
                    if ($x < $minX) $minX = $x;
                    if ($x > $maxX) $maxX = $x;
                    if ($y < $minY) $minY = $y;
                    if ($y > $maxY) $maxY = $y;
                }
            }
        }

        if ($maxX < 0) {
            return null;
        }

        return [$minX, $minY, $maxX - $minX + 1, $maxY - $minY + 1];
    }

    /**
     * bbox 영역을 셀 단위 최빈색으로 줄인다. 셀은 정사각형(가로세로 같은 크기)이라 비율이 유지된다.
     * 배경 픽셀이 셀의 절반 이상이면 그 셀은 투명(배경)으로 둔다.
     */
    private function downsample(GdImage $src, int $bx, int $by, int $bw, int $bh): GdImage
    {
        $cell = $bh / min($this->spriteHeight, $bh);
        $outH = (int) max(1, round($bh / $cell));
        $outW = (int) max(1, min($this->grid, round($bw / $cell)));

        $dst = imagecreatetruecolor($outW, $outH);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
        imagefill($dst, 0, 0, $transparent);

        for ($oy = 0; $oy < $outH; $oy++) {
            $y0 = $by + (int) floor($oy * $cell);
            $y1 = min($by + $bh, $by + (int) floor(($oy + 1) * $cell));
            for ($ox = 0; $ox < $outW; $ox++) {
                $x0 = $bx + (int) floor($ox * $cell);
                $x1 = min($bx + $bw, $bx + (int) floor(($ox + 1) * $cell));

                $buckets = []; // key => [count, r, g, b]
                $dark = [0, 0, 0, 0]; // 외곽선 후보(어두운 픽셀) [count, r, g, b]
                $bg = 0;
                $total = 0;
                for ($y = $y0; $y < max($y1, $y0 + 1); $y++) {
                    for ($x = $x0; $x < max($x1, $x0 + 1); $x++) {
                        $total++;
                        $rgb = imagecolorat($src, $x, $y);
                        if ($this->isBackground($rgb)) {
                            $bg++;
                            continue;
                        }
                        $r = ($rgb >> 16) & 0xFF;
                        $g = ($rgb >> 8) & 0xFF;
                        $b = $rgb & 0xFF;
                        if ($r * 299 + $g * 587 + $b * 114 < self::DARK_LUMA * 1000) {
                            $dark[0]++;
                            $dark[1] += $r;
                            $dark[2] += $g;
                            $dark[3] += $b;
                        }
                        $s = self::BUCKET_SHIFT;
                        $key = (($r >> $s) << 16) | (($g >> $s) << 8) | ($b >> $s);
                        $buckets[$key] ??= [0, 0, 0, 0];
                        $buckets[$key][0]++;
                        $buckets[$key][1] += $r;
                        $buckets[$key][2] += $g;
                        $buckets[$key][3] += $b;
                    }
                }

                if ($bg * 2 >= $total || ! $buckets) {
                    continue;
                }

                // 얇은 외곽선이 다수결에서 지지 않도록, 어두운 픽셀이 일정 비율 이상이면 그 색을 쓴다.
                if ($dark[0] >= ($total - $bg) * self::DARK_SHARE) {
                    $best = $dark;
                } else {
                    $best = null;
                    foreach ($buckets as $bucket) {
                        if ($best === null || $bucket[0] > $best[0]) {
                            $best = $bucket;
                        }
                    }
                }
                [$n, $r, $g, $b] = $best;
                imagesetpixel($dst, $ox, $oy, imagecolorallocate($dst, intdiv($r, $n), intdiv($g, $n), intdiv($b, $n)));
            }
        }

        return $dst;
    }

    /** 팔레트를 줄이고(디더링 없음) 다시 truecolor 로 돌린다. 투명 배경은 유지된다. */
    private function quantize(GdImage $img): void
    {
        imagetruecolortopalette($img, false, $this->colors);
        imagepalettetotruecolor($img);
    }

    private function placeOnCanvas(GdImage $sprite): GdImage
    {
        $canvas = imagecreatetruecolor($this->grid, $this->grid);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagealphablending($canvas, true);

        $w = imagesx($sprite);
        $h = imagesy($sprite);
        $x = intdiv($this->grid - $w, 2);
        $y = intdiv($this->grid - $h, 2);
        imagecopy($canvas, $sprite, $x, $y, 0, 0, $w, $h);

        return $canvas;
    }

    private function isBackground(int $rgb): bool
    {
        if ((($rgb >> 24) & 0x7F) > 63) {
            return true; // 반 이상 투명
        }

        return (($rgb >> 16) & 0xFF) > self::BG_THRESHOLD
            && (($rgb >> 8) & 0xFF) > self::BG_THRESHOLD
            && ($rgb & 0xFF) > self::BG_THRESHOLD;
    }
}
