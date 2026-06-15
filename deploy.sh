#!/usr/bin/env bash
# OOTD 배포 스크립트 (EC2에서 실행). pull → build → install → migrate → up.
set -euo pipefail
cd "$(dirname "$0")"

COMPOSE="docker compose -f compose.prod.yaml"

if [ ! -f .env ]; then
    echo "✗ .env 가 없습니다. 'cp .env.production.example .env' 후 값을 채우세요." >&2
    exit 1
fi

# 컨테이너를 "실제 사용자" uid/gid 로 실행한다.
# 중요: 컨테이너를 root(0)로 띄우면 php-fpm 마스터만 root 고 워커는 www-data 로
# 강등되어 storage/ 등에 못 쓴다(권한 500). non-root 로 띄우면 워커도 그 uid 로 돈다.
# sudo 로 실행해도 SUDO_UID 로 원래 사용자를 잡아 0(root) 회피.
export APP_UID="${SUDO_UID:-$(id -u)}"
export APP_GID="${SUDO_GID:-$(id -g)}"

if [ "$APP_UID" = "0" ]; then
    echo "⚠ 컨테이너가 root(uid 0)로 실행됩니다 — fpm 워커가 www-data 로 강등되어" >&2
    echo "  storage/DB 쓰기 권한 오류(500)가 납니다. 일반 사용자로 실행하세요." >&2
fi

# 프로젝트 파일이 그 uid 소유가 아니면 컨테이너도 못 쓴다.
if [ ! -w . ] || { [ -d database ] && [ ! -w database ]; }; then
    echo "✗ 프로젝트 디렉터리에 쓰기 권한이 없습니다." >&2
    echo "  아래 실행 후 다시 시도하세요:" >&2
    echo "    sudo chown -R ${APP_UID}:${APP_GID} ." >&2
    exit 1
fi

echo "▶ 코드 가져오기"
git pull --ff-only

echo "▶ 이미지 빌드"
$COMPOSE build

echo "▶ PHP 의존성 설치"
$COMPOSE run --rm app composer install --no-dev --optimize-autoloader --no-interaction

# APP_KEY 가 비어 있으면 생성 (vendor 설치 이후라야 가능)
if ! grep -qE '^APP_KEY=base64:' .env; then
    echo "▶ APP_KEY 생성"
    $COMPOSE run --rm app php artisan key:generate --force
fi

# SQLite 파일 보장 (git에는 안 올라가므로 최초 배포 시 없음)
[ -f database/database.sqlite ] || { touch database/database.sqlite; echo "  ↳ database/database.sqlite 생성"; }

echo "▶ DB 마이그레이션"
$COMPOSE run --rm app php artisan migrate --force

# 최초 배포에만 시드(프로필이 없을 때). 이후 핸들 변경분을 덮어쓰지 않는다.
if [ "$($COMPOSE run --rm app php artisan tinker --execute='echo App\Models\Profile::count();' 2>/dev/null | tr -dc '0-9')" = "0" ]; then
    echo "  ↳ 최초 프로필 시드"
    $COMPOSE run --rm app php artisan db:seed --force
fi

echo "▶ storage 링크"
$COMPOSE run --rm app php artisan storage:link || true

echo "▶ 설정/라우트/뷰 캐시"
$COMPOSE run --rm app php artisan optimize

echo "▶ 서비스 기동"
$COMPOSE up -d

echo "✅ 배포 완료 → https://ootd.viram.dev (eta-caddy 라우팅 설정 후 접속)"
