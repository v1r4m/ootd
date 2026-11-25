#!/bin/sh
# 컨테이너 시작 시 준비 작업 후 php-fpm + Caddy 기동.
# storage 는 영구 볼륨이라 첫 기동 땐 비어 있다 → 필요한 디렉터리/DB 파일을 만든다.
set -e
cd /var/www/html

if [ -z "$APP_KEY" ]; then
    echo "✗ APP_KEY 환경변수가 없습니다. 'php artisan key:generate --show' 값을 Coolify 환경변수에 넣으세요." >&2
    exit 1
fi
[ -n "$FAL_KEY" ] || echo "⚠ FAL_KEY 가 없습니다 — 아바타는 placeholder 로 생성됩니다." >&2

mkdir -p storage/app/public storage/app/private storage/framework/cache/data \
         storage/framework/sessions storage/framework/views storage/logs storage/database
[ -f "$DB_DATABASE" ] || touch "$DB_DATABASE"
chown -R www-data:www-data storage bootstrap/cache

# artisan 은 fpm 워커와 같은 www-data 로 (root 로 만든 캐시/DB 파일 권한 문제 방지).
# www-data 는 홈이 쓰기 불가라 tinker(psysh) 가 실패한다 → HOME 을 /tmp 로.
as_www() { runuser -u www-data -- env HOME=/tmp "$@"; }

as_www php artisan migrate --force
if [ "$(as_www php artisan tinker --execute='echo App\Models\Profile::count();' 2>/dev/null | tr -dc '0-9')" = "0" ]; then
    echo "▶ 최초 프로필 시드"
    as_www php artisan db:seed --force
fi
php artisan storage:link --force >/dev/null   # public/ 은 root 소유라 root 로
as_www php artisan optimize

php-fpm -D
exec caddy run --config /etc/caddy/Caddyfile --adapter caddyfile
