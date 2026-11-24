<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_IMAGE_MODEL', 'gemini-2.5-flash-image'),
    ],

    'fal' => [
        'key' => env('FAL_KEY'),
        'model' => env('FAL_IMAGE_MODEL', 'fal-ai/flux/schnell'),
        'steps' => env('FAL_STEPS', 4),
        // 기준 아바타가 있을 때 쓰는 edit 모델 (avatar:reference 로 지정).
        // 4b 는 헤어스타일·바지 기장 같은 큰 변경을 자주 놓쳐서 9b ($0.011/MP, 4b 대비 +10%).
        'edit_model' => env('FAL_EDIT_MODEL', 'fal-ai/flux-2/klein/9b/edit'),
        // 한→영 번역용 (fal 의 openrouter/router 경유)
        'text_model' => env('FAL_TEXT_MODEL', 'openai/gpt-4o-mini'),
    ],

    // fal | gemini | placeholder. 비우면 키가 있는 것 중 fal → gemini 순으로 자동 선택.
    'avatar' => [
        'engine' => env('AVATAR_ENGINE'),
    ],

    // 생성 이미지 도트 정규화 (App\Services\PixelSnap). API 비용 없음.
    'pixel' => [
        'enabled' => env('PIXEL_SNAP', true),
        'sprite_height' => env('PIXEL_SPRITE_HEIGHT', 100), // 캐릭터 키(도트 픽셀 수)
        'grid' => env('PIXEL_GRID', 128),                   // 캔버스 한 변(도트 픽셀 수)
        'scale' => env('PIXEL_SCALE', 8),                   // 확대 배율 → 출력 128*8 = 1024px
        'colors' => env('PIXEL_COLORS', 24),                // 팔레트 색 수
    ],

];
