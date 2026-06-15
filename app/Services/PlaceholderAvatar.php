<?php

namespace App\Services;

/**
 * GEMINI_API_KEY 없이도 달력이 동작하도록, 옷 설명에서 색을 추려
 * 간단한 도트 아바타 PNG를 GD로 그려주는 폴백.
 */
class PlaceholderAvatar
{
    private const SCALE = 12;

    private const COLORS = [
        '검정|검은|블랙' => [45, 45, 45],
        '하양|하얀|흰|화이트' => [245, 245, 245],
        '회색|그레이' => [158, 158, 158],
        '빨강|빨간|레드' => [229, 57, 53],
        '주황|오렌지' => [251, 140, 0],
        '노랑|노란|옐로' => [253, 216, 53],
        '초록|녹색|그린' => [67, 160, 71],
        '하늘' => [144, 202, 249],
        '파랑|파란|블루' => [30, 136, 229],
        '남색|네이비' => [40, 53, 147],
        '청|데님|진' => [79, 109, 154],
        '보라|퍼플' => [142, 36, 170],
        '분홍|핑크' => [244, 143, 177],
        '갈색|브라운' => [121, 85, 72],
        '베이지' => [215, 196, 163],
        '카키' => [138, 138, 92],
        '민트' => [127, 216, 196],
    ];

    private const GARMENTS = [
        'top' => '티셔츠|티|셔츠|니트|맨투맨|후드|후디|스웨터|블라우스|상의|자켓|재킷|코트|가디건|점퍼|원피스',
        'bottom' => '청바지|바지|팬츠|슬랙스|치마|스커트|레깅스|반바지|하의',
        'shoes' => '신발|크록스|운동화|스니커즈|구두|부츠|샌들|슬리퍼|로퍼',
    ];

    public function render(string $description): string
    {
        $parsed = $this->parseColors($description);

        $img = imagecreatetruecolor(36 * self::SCALE, 36 * self::SCALE);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));

        $skin = imagecolorallocate($img, 255, 224, 196);
        $hair = imagecolorallocate($img, 60, 48, 44);
        $dark = imagecolorallocate($img, 35, 30, 30);
        $blush = imagecolorallocate($img, 250, 180, 180);
        $white = imagecolorallocate($img, 255, 255, 255);
        $top = $this->allocate($img, $parsed['top'] ?? [158, 158, 158]);
        $bottom = $this->allocate($img, $parsed['bottom'] ?? [79, 109, 154]);
        $shoes = $this->allocate($img, $parsed['shoes'] ?? [45, 45, 45]);

        // 머리 + 헤어 (메이플 비율: 머리가 전체의 ~60%)
        $this->cell($img, 8, 4, 27, 17, $skin);
        $this->cell($img, 7, 2, 28, 8, $hair);   // 윗머리
        $this->cell($img, 6, 5, 8, 14, $hair);   // 왼쪽 옆머리
        $this->cell($img, 27, 5, 29, 14, $hair); // 오른쪽 옆머리
        $this->cell($img, 9, 3, 18, 5, $this->lighten($img, 60, 48, 44)); // 하이라이트

        // 얼굴: 넓은 간격의 큰 눈 + 하이라이트, 홍조, 작은 입
        $this->cell($img, 11, 11, 13, 14, $dark);
        $this->cell($img, 22, 11, 24, 14, $dark);
        $this->cell($img, 12, 12, 12, 12, $white);
        $this->cell($img, 23, 12, 23, 12, $white);
        $this->cell($img, 9, 15, 10, 15, $blush);
        $this->cell($img, 25, 15, 26, 15, $blush);
        $this->cell($img, 17, 16, 18, 16, $dark);

        // 몸통(상의) — 목 없이 바로 연결
        $this->cell($img, 13, 18, 22, 25, $top);
        // 팔
        $this->cell($img, 11, 18, 12, 23, $top);
        $this->cell($img, 23, 18, 24, 23, $top);
        $this->cell($img, 11, 24, 12, 25, $skin);
        $this->cell($img, 23, 24, 24, 25, $skin);

        // 하의
        $this->cell($img, 13, 26, 22, 30, $bottom);
        $this->cell($img, 17, 28, 18, 30, $white); // 다리 사이

        // 신발
        $this->cell($img, 12, 31, 17, 33, $shoes);
        $this->cell($img, 19, 31, 24, 33, $shoes);

        ob_start();
        imagepng($img);

        return ob_get_clean();
    }

    /** @return array<string, array{int,int,int}> */
    private function parseColors(string $description): array
    {
        $colorPattern = implode('|', array_keys(self::COLORS));
        $result = [];

        foreach (self::GARMENTS as $slot => $garmentPattern) {
            if (! preg_match("/(?:({$colorPattern})\\s*색?\\s*)?(?:{$garmentPattern})/u", $description, $m) || empty($m[1])) {
                continue;
            }
            foreach (self::COLORS as $pattern => $rgb) {
                if (preg_match("/^(?:{$pattern})$/u", $m[1])) {
                    $result[$slot] = $rgb;
                    break;
                }
            }
        }

        return $result;
    }

    private function allocate(\GdImage $img, array $rgb): int
    {
        return imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
    }

    private function lighten(\GdImage $img, int $r, int $g, int $b): int
    {
        return imagecolorallocate($img, min(255, $r + 50), min(255, $g + 50), min(255, $b + 50));
    }

    private function cell(\GdImage $img, int $x1, int $y1, int $x2, int $y2, int $color): void
    {
        imagefilledrectangle(
            $img,
            $x1 * self::SCALE,
            $y1 * self::SCALE,
            ($x2 + 1) * self::SCALE - 1,
            ($y2 + 1) * self::SCALE - 1,
            $color,
        );
    }
}
