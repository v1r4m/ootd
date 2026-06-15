# OOTD — 오늘의 패션 도트 달력

매일의 옷차림을 글로 기록하면, 메이플스토리 플레이어 아바타 스타일의 도트 캐릭터로 변환해서
큼지막한 달력에 채워주는 블로그.

## 스택

- PHP 8.5 / Laravel 13 (SQLite)
- Laravel Sail (Docker) — 로컬에 PHP 설치 불필요
- Gemini 이미지 생성 API (`gemini-2.5-flash-image`)
- Blade + 순수 CSS (빌드 스텝 없음), Galmuri 픽셀 폰트

## 실행

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail artisan storage:link
```

→ http://localhost:8088 (포트는 `.env`의 `APP_PORT`)

처음 받았다면 먼저 의존성 설치:

```bash
docker run --rm -v "$PWD:/app" -w /app composer:latest composer install
cp .env.example .env
docker run --rm -v "$PWD:/var/www/html" -w /var/www/html laravelsail/php84-composer:latest php artisan key:generate
```

## Gemini 연동

`.env`에 키를 넣으면 진짜 메이플 스타일 아바타가 생성된다 (키는 https://aistudio.google.com 에서 발급):

```
GEMINI_API_KEY=발급받은키
```

키가 없으면 옷 설명에서 색을 파싱해 GD로 그린 임시 도트 아바타가 대신 들어간다.

## 구조

- `app/Services/AvatarGenerator.php` — 메이플 아바타 프롬프트 빌더 + Gemini 호출
- `app/Services/PlaceholderAvatar.php` — API 키 없을 때의 GD 폴백
- `app/Http/Controllers/CalendarController.php` — `/@{handle}/{year?}/{month?}` 달력
- `app/Http/Controllers/OutfitController.php` — `/days/{date}` 기록 작성/재생성/삭제
- `/settings` — 핸들, 이름, 아바타 기본 외형(머리/눈 색 등) 설정

## 테스트

```bash
./vendor/bin/sail artisan test
```
