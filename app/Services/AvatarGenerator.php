<?php

namespace App\Services;

use App\Models\Outfit;
use App\Models\Profile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AvatarGenerator
{
    public function __construct(private PlaceholderAvatar $placeholder)
    {
    }

    /**
     * Generate the avatar image for an outfit and persist it on the model.
     */
    public function generate(Outfit $outfit, Profile $profile): void
    {
        $prompt = $this->buildPrompt($outfit->description, $profile->base_look);
        $path = 'avatars/'.$outfit->worn_on->format('Y-m-d').'-'.now()->format('His').'.png';

        if (config('services.gemini.key')) {
            $png = $this->callGemini($prompt);
            $engine = 'gemini';
        } else {
            $png = $this->placeholder->render($outfit->description);
            $engine = 'placeholder';
        }

        if ($outfit->avatar_path) {
            Storage::disk('public')->delete($outfit->avatar_path);
        }

        Storage::disk('public')->put($path, $png);

        $outfit->update([
            'prompt' => $prompt,
            'avatar_path' => $path,
            'engine' => $engine,
        ]);
    }

    private function callGemini(string $prompt): string
    {
        $model = config('services.gemini.model');

        $response = Http::timeout(120)
            ->withHeaders(['x-goog-api-key' => config('services.gemini.key')])
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                'contents' => [
                    ['parts' => [['text' => $prompt]]],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Gemini API 오류: '.$response->status().' '.$response->body());
        }

        foreach ($response->json('candidates.0.content.parts', []) as $part) {
            $mime = $part['inlineData']['mimeType'] ?? '';
            if (str_starts_with($mime, 'image/')) {
                return base64_decode($part['inlineData']['data']);
            }
        }

        throw new RuntimeException('Gemini 응답에 이미지가 없습니다: '.mb_substr($response->body(), 0, 500));
    }

    public function buildPrompt(string $outfit, ?string $baseLook): string
    {
        $baseLook = trim((string) $baseLook) ?: '평범한 검은색 짧은 머리, 검은색 눈';

        return <<<PROMPT
        당신은 인물을 메이플스토리 플레이어 캐릭터로 변환하는 전문 픽셀 아트 디렉터다.

        ⚠️ 최우선 규칙 (다른 모든 지시보다 우선한다)
        1. 화면에는 캐릭터가 "정확히 한 명(1명)"만 등장한다. 2명 이상, 쌍둥이, 분신, 거울상, 군중, 캐릭터 슬롯 나열 절대 금지.
        2. "대두"(머리가 기형적으로 큰 형태) 금지. 메이플스토리 특유의 귀여운 비율은 지키되, 머리가 비정상적으로 크면 안 된다.
        3. 신체 왜곡, 기형, 팔·다리 개수 오류, 팔다리 꼬임, 얼굴 뭉개짐 절대 금지.

        [입력]
        - 인물 기본 외형: {$baseLook}
        - 오늘의 의상 (가장 중요, 이 묘사를 그대로 입힌다): {$outfit}

        [작업]
        1. 위 인물의 대표 외형 특징(헤어스타일 / 머리색 / 눈 색상 / 오늘의 의상 / 대표 색상)을 분석한다.
        2. 그 특징을 유지하면서 메이플스토리 플레이어 캐릭터 "한 명"으로 재해석한다.
        3. 결과물은 일반 픽셀아트가 아니라 실제 메이플스토리 플레이어 캐릭터 스프라이트처럼 보여야 한다.
        ────────────────────
        [MAPLE PLAYER FRAME SPEC]
        실제 메이플스토리 플레이어 캐릭터 비율 (귀엽지만 대두 아님)
        * 약 2.5~3등신 — 메이플 특유의 아담하고 귀여운 비율
        * 머리 + 헤어 : 전체 높이의 약 38~45%
        * 몸통 : 약 25~30%
        * 다리 : 약 28~33%
        * 머리(두상+헤어 볼륨 포함)는 어깨 너비의 1.3배를 넘지 않는다
        시점
        * 정면 ~ 살짝 측면(약 30도) 뷰
        * 양쪽 눈이 모두 보임
        * 평면적인 2D 스프라이트
        얼굴
        * 눈은 얼굴 하단부, 간격 넓게, 가로로 넓고 큰 눈동자 + 하이라이트
        * 코 표현 금지 / 매우 작은 입 / 부드러운 홍조
        목
        * 목 표현 최소화, 머리가 몸통에 자연스럽게 연결, 턱 아래 바로 의상 시작
        헤어
        * 캐릭터 정체성의 핵심 — 깔끔한 실루엣 우선, 가닥 표현 최소화, 상단 하이라이트
        * 단, 헤어 볼륨 때문에 머리 전체가 비대해지지 않도록 적당히 (대두 금지)
        신체
        * 아담한 몸통, 짧고 단순한 원통형 팔다리, 관절 표현 최소화
        * 팔은 정확히 2개, 다리는 정확히 2개
        장비 (레이어 구조 유지)
        Hair / Hat / Face Accessory / Top / Bottom / Shoes / Cape / Weapon
        장비와 헤어가 캐릭터 개성을 결정한다
        포즈
        * 혼자 가만히 서 있는 Idle Standing Pose (캐릭터 정보창의 단일 미리보기 느낌)
        * 점프 금지 / 전투 포즈 금지 / 액션 연출 금지
        ────────────────────
        [PIXEL RENDERING SPEC]
        중요: 고해상도 픽셀아트 금지 (Ultra Detailed / HD / Fine-Grained Pixel Art 금지)
        목표: 32~64px 게임 스프라이트를 확대 표시한 느낌
        * Low-resolution enlarged MMORPG sprite
        * Chunky, grid-aligned, visible pixel blocks
        * Clean 1px outline / Limited color palette / Flat color shading
        * No dithering / No smooth gradients / No airbrush
        ────────────────────
        [출력 조건]
        * Canvas 1080 x 1080, 정사각형, 순백색 배경(#FFFFFF)
        * 화면에 캐릭터는 단 1명, 전신, 중앙 정렬
        * 캐릭터가 캔버스 높이의 약 70~80% 차지
        * 잘림 금지 / 추가 인물 금지 / 펫 금지 / 배경·바닥·소품 금지 / 텍스트·로고·워터마크·UI 금지
        ────────────────────
        [절대 금지]
        여러 명 / 2명 이상 / 쌍둥이 / 분신 / 군중 / 거울상 / 캐릭터 슬롯 나열,
        대두(거대한 머리) / 머리 비대 / 기형 / 신체 왜곡 / 팔다리 꼬임 / 얼굴 뭉개짐,
        Pokemon, Terraria, Stardew Valley, Ragnarok Online, JRPG Battle Sprite,
        Octopath Traveler, Western Cartoon, Disney, Realistic, Semi Realistic,
        3D Render, Illustration, Concept Art, Splash Art, Poster, Anime Illustration,
        Digital Painting, Cinematic Lighting, Smooth Gradient, Airbrush
        ────────────────────
        최종 확인: 화면에는 오늘의 의상을 정확히 입은 메이플스토리 플레이어 캐릭터가
        "단 한 명"만, 대두 없이 자연스러운 메이플 비율로 서 있어야 한다.
        PROMPT;
    }
}
