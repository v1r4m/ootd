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

    /** 모델 출력을 격자 정렬된 도트로 정규화한다. 끄려면 PIXEL_SNAP=false. */
    private function snap(string $png): string
    {
        if (! config('services.pixel.enabled')) {
            return $png;
        }

        return PixelSnap::fromConfig()->process($png);
    }

    /**
     * Generate the avatar image for an outfit and persist it on the model.
     */
    public function generate(Outfit $outfit, Profile $profile): void
    {
        $path = 'avatars/'.$outfit->worn_on->format('Y-m-d').'-'.now()->format('His').'.png';

        switch ($this->engine()) {
            case 'fal':
                [$reference, $hairMatched] = $this->pickReference($profile, $outfit->description);
                if ($reference) {
                    // 기준 아바타가 있으면 얼굴·화풍은 그대로 두고 옷만 바꾼다.
                    $prompt = $this->buildEditPrompt($outfit->description, $profile->base_look, $hairMatched);
                    $png = $this->cleanup($this->callFalEdit($prompt, $reference));
                    $engine = 'fal-edit';
                } else {
                    $prompt = $this->buildFluxPrompt($outfit->description, $profile->base_look);
                    $png = $this->snap($this->callFal($prompt));
                    $engine = 'fal';
                }
                break;
            case 'gemini':
                $prompt = $this->buildPrompt($outfit->description, $profile->base_look);
                $png = $this->snap($this->callGemini($prompt));
                $engine = 'gemini';
                break;
            default:
                $prompt = $this->buildPrompt($outfit->description, $profile->base_look);
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

    /**
     * AVATAR_ENGINE=fal|gemini|placeholder. 비우면(auto) fal → gemini → placeholder 순으로 키가 있는 것.
     * 고른 엔진의 키가 없으면 placeholder 로 떨어진다.
     */
    private function engine(): string
    {
        $engine = config('services.avatar.engine') ?: 'auto';

        return match (true) {
            in_array($engine, ['fal', 'auto'], true) && config('services.fal.key') => 'fal',
            in_array($engine, ['gemini', 'auto'], true) && config('services.gemini.key') => 'gemini',
            default => 'placeholder',
        };
    }

    /** 기준 아바타 헤어스타일 종류. default 는 헤어 언급이 없을 때 쓰는 기본. */
    public const REFERENCE_STYLES = ['default', 'bun', 'ponytail', 'down'];

    /** 의상 설명의 헤어 키워드 → 기준 아바타 종류. 위에서부터 먼저 맞는 것. */
    private const HAIR_KEYWORDS = [
        'ponytail' => '포니테일|포니|하나로 묶|ponytail',
        'bun' => '똥머리|번 ?헤어|올림머리|만두머리|\\bbun\\b',
        'down' => '풀었|풀고|풀어|내렸|생머리|단발|hair down',
    ];

    public static function referencePath(Profile $profile, string $style = 'default'): string
    {
        return "references/{$profile->handle}/{$style}.png";
    }

    /**
     * 오늘 헤어에 맞는 기준 아바타(512px data URI)를 고른다. `php artisan avatar:reference` 로 지정.
     * 헤어 언급이 있고 그 헤어의 기준 이미지가 있으면 그걸, 아니면 default 를 쓴다.
     *
     * @return array{0: ?string, 1: bool} [data URI 또는 null, 헤어가 기준 이미지와 일치하는지]
     */
    private function pickReference(Profile $profile, string $outfit): array
    {
        $disk = Storage::disk('local');
        $style = self::hairStyle($outfit);

        if ($style && $disk->exists($path = self::referencePath($profile, $style))) {
            return [$this->dataUri($disk->get($path)), true];
        }

        if (! $disk->exists($path = self::referencePath($profile))) {
            return [null, false];
        }

        // 헤어 언급이 없으면 default 헤어 그대로가 정답, 언급이 있는데 맞는 기준이 없으면 모델이 바꿔야 한다.
        return [$this->dataUri($disk->get($path)), ! self::mentionsHairstyle($outfit)];
    }

    public static function hairStyle(string $outfit): ?string
    {
        foreach (self::HAIR_KEYWORDS as $style => $pattern) {
            if (preg_match("/{$pattern}/iu", $outfit)) {
                return $style;
            }
        }

        return null;
    }

    private function dataUri(string $png): string
    {
        return 'data:image/png;base64,'.base64_encode($png);
    }

    private function cleanup(string $png): string
    {
        if (! config('services.pixel.enabled')) {
            return $png;
        }

        return PixelSnap::fromConfig()->clean($png);
    }

    /** fal.ai FLUX.2 klein edit: 기준 이미지 + 지시문. 출력 1MP 기준 $0.01 (+ 512px 입력). */
    private function callFalEdit(string $prompt, string $referenceDataUri): string
    {
        $model = config('services.fal.edit_model');

        $response = Http::timeout(120)
            ->withHeaders(['Authorization' => 'Key '.config('services.fal.key')])
            ->post("https://fal.run/{$model}", [
                'prompt' => $prompt,
                'image_urls' => [$referenceDataUri],
                // 화면 표시는 최대 280px 라 512 로 충분. MP 단가라 1024 대비 출력분 1/4.
                'image_size' => ['width' => 512, 'height' => 512],
                'num_images' => 1,
                'output_format' => 'png',
                'sync_mode' => true,
            ]);

        return $this->falImage($response);
    }

    /**
     * edit 지시문. 한국어 의상 용어(조거팬츠, 어그부츠 등)를 klein 이 정확히 몰라서 영어로 옮겨 넣는다.
     * 헤어는 가능하면 헤어스타일별 기준 이미지로 해결하고(모델이 큰 헤어 변경을 잘 못 한다),
     * 맞는 기준 이미지가 없을 때만 번역기가 돌려준 외형(LOOK)대로 바꾸게 한다.
     */
    public function buildEditPrompt(string $outfit, ?string $baseLook, bool $hairMatched = true): string
    {
        $baseLook = trim((string) $baseLook);
        [$look, $wear] = $this->toEnglish($baseLook, $outfit);

        // 기준 이미지가 이미 오늘 헤어면 "유지"만, 아니면 바꾸게 한다.
        $hair = ! $hairMatched
            ? "Change only the hairstyle to match: {$look}. Keep the same hair color; this is the only change allowed above the neck. "
            : 'Keep the exact same hairstyle as the reference. '; // 기본 외형(예: 단발)을 넣으면 기준 이미지 헤어와 충돌한다

        return 'Redraw this exact same character in the exact same pixel art sprite style. '
            .'Keep the body proportions identical to the reference: same big head size, same short torso, '
            .'same short stubby legs, same total height and same position on the canvas. '
            .'Same face and eyes, same outline and shading, same pixel size. '
            ."Replace all clothes, shoes and accessories with: {$wear}. "
            .'Remove every bag, backpack, keychain, scarf or accessory from the reference that is not in that list. '
            .'Draw each garment with its real type and length (long coats and long pants reach the ankles, shorts are short), '
            .'scaled to fit this small chibi body; never make the body taller, slimmer or longer. '
            .$hair
            .'Full body, standing idle, plain white background, no text or logos.';
    }

    /** 의상 설명에 헤어스타일 언급이 있는지. (머리띠 등 머리 위 소품도 포함돼도 무방) */
    public static function mentionsHairstyle(string $text): bool
    {
        return (bool) preg_match('/머리|헤어|포니테일|똥머리|단발|땋|양갈래|올림|반묶음|묶었|풀었|번헤어|ponytail|bun|braid|hair/iu', $text);
    }

    /** fal.ai Flux schnell. 1MP 이하 1장 = $0.003. */
    private function callFal(string $prompt): string
    {
        $model = config('services.fal.model');

        $response = Http::timeout(120)
            ->withHeaders(['Authorization' => 'Key '.config('services.fal.key')])
            ->post("https://fal.run/{$model}", [
                'prompt' => $prompt,
                'image_size' => 'square_hd', // 1024x1024 = 1MP (어차피 1MP 단위 올림 과금)
                'num_inference_steps' => (int) config('services.fal.steps'), // 과금과 무관, 4~8 이면 충분
                'num_images' => 1,
                'output_format' => 'png',
                'sync_mode' => true, // URL 대신 data URI 로 바로 받는다
            ]);

        return $this->falImage($response);
    }

    private function falImage(\Illuminate\Http\Client\Response $response): string
    {
        if ($response->failed()) {
            throw new RuntimeException('fal API 오류: '.$response->status().' '.mb_substr($response->body(), 0, 500));
        }

        $url = $response->json('images.0.url');
        if (! $url) {
            throw new RuntimeException('fal 응답에 이미지가 없습니다: '.mb_substr($response->body(), 0, 500));
        }

        if (str_starts_with($url, 'data:')) {
            return base64_decode(substr($url, strpos($url, ',') + 1));
        }

        return Http::timeout(60)->get($url)->throw()->body();
    }

    /** Flux(T5)는 영어 위주라 짧은 영어 태그형 프롬프트로 만든다. */
    public function buildFluxPrompt(string $outfit, ?string $baseLook): string
    {
        $baseLook = trim((string) $baseLook) ?: '평범한 검은색 짧은 머리, 검은색 눈';
        [$look, $wear] = $this->toEnglish($baseLook, $outfit);

        // 여러 버전을 A/B 비교해서 고른 프롬프트. 규칙:
        // - Flux 는 지시문을 "이해"하지 않으니 짧은 영어 긍정 묘사만, 중요한 것부터 앞에.
        //   schnell 은 앞쪽 약 256 토큰만 읽으니 그 안에 특징을 빽빽하게. (길이는 과금과 무관)
        // - 금지 목록·스타일 이름 나열은 오히려 그 화풍을 끌어온다. 게임 이름도 schnell 은 모르고 IP 문제가 있어 넣지 않는다.
        // - "n등신" 같은 숫자는 거의 무시된다. 그림자는 PixelSnap 이 지운다.
        return 'low-resolution pixel art sprite of a cute Korean MMORPG player avatar, single chibi character '
            .'facing slightly left in three-quarter view, standing idle with arms down at the sides like a character selection screen, '
            ."{$look}, wearing {$wear}. "
            .'Round head a bit larger than the body, thick full bangs covering the forehead, '
            .'hair drawn as a few large smooth chunky shapes with one bright shine strip on the crown. '
            .'Wide soft face with a rounded chin, large tall eyes in the lower half of the face, thick dark upper lash line, '
            .'colored iris with two white highlight pixels, no nose, tiny short mouth line, light pink blush. '
            .'No neck. Tiny torso, short stubby arms with small round fists, short legs slightly apart, small rounded shoes. '
            .'Colored outlines in a darker shade of each fill color, one shadow tone per color, light from top left, '
            .'bright soft pastel palette, clean flat 2D sprite, crisp pixels, white background';
    }

    /**
     * 한국어 외형/의상 설명을 짧은 영어 묘사로 옮긴다.
     * fal 의 OpenRouter 엔드포인트로 싼 LLM 호출(gpt-4o-mini 기준 호출당 ~$0.00004).
     * 실패하면 원문을 그대로 쓴다.
     *
     * @return array{0:string,1:string}
     */
    private function toEnglish(string $look, string $outfit): array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders(['Authorization' => 'Key '.config('services.fal.key')])
                ->post('https://fal.run/openrouter/router', [
                    'model' => config('services.fal.text_model'),
                    // system_prompt 로 주면 규칙을 자주 무시해서, 지시와 출력 틀을 전부 prompt 에 넣는다.
                    'prompt' => "Rewrite this Korean character description as English image-prompt phrases.\n"
                        ."Base look: {$look}\n"
                        ."Today's outfit: {$outfit}\n\n"
                        ."Rules: If today's text mentions a hairstyle, it REPLACES the base hairstyle (keep the base hair color).\n"
                        ."Answer in exactly this format, two lines, nothing else:\n"
                        ."LOOK: <gender>, <hair color> hair <hairstyle>, <eye color> eyes\n"
                        .'OUTFIT: <garments, colors, shoes, accessories, comma-separated>',
                ])->throw();

            // JSON 은 gpt-4o-mini 가 가끔 깨뜨려서 두 줄 텍스트로 받는다.
            $text = (string) $response->json('output');
            if (preg_match('/LOOK:\s*(.+)/i', $text, $l) && preg_match('/OUTFIT:\s*(.+)/i', $text, $o)) {
                return [trim($l[1], " \t\"'"), trim($o[1], " \t\"'")];
            }
            logger()->warning('toEnglish: 번역 응답 형식 오류', ['output' => $text]);
        } catch (\Throwable $e) {
            report($e);
        }

        return [$look, $outfit];
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
                // 비율을 명시해야 모델이 native 1024x1024(1:1)로 안정적으로 뽑는다.
                // 이 지시가 없으면 가끔 작은 이미지가 나온다.
                'generationConfig' => [
                    'imageConfig' => ['aspectRatio' => '1:1'],
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
