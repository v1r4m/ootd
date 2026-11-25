# Coolify(또는 아무 Docker 호스트)용 단일 컨테이너 이미지: php-fpm + Caddy.
# 코드·vendor 를 이미지에 굽고, 데이터(SQLite·아바타·기준 아바타·로그)는 전부 storage 볼륨 하나에 둔다.
#   - 포트: 80 (TLS·도메인은 Coolify 프록시가 처리)
#   - 영구 볼륨: /var/www/html/storage  ← 반드시 마운트
#   - 시작 시 마이그레이션/storage:link/캐시를 자동 수행 (docker/coolify/entrypoint.sh)
# 기존 EC2(eta-caddy) 배포는 compose.prod.yaml + docker/Dockerfile 을 그대로 쓴다.
FROM php:8.5-fpm

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/bin/
RUN install-php-extensions pdo_sqlite gd zip intl bcmath opcache pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=caddy:2 /usr/bin/caddy /usr/bin/caddy

COPY docker/php.ini /usr/local/etc/php/conf.d/zzz-app.ini
COPY docker/fpm-logging.conf /usr/local/etc/php-fpm.d/zzz-logging.conf
COPY docker/coolify/Caddyfile /etc/caddy/Caddyfile
COPY docker/coolify/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html

# 의존성 레이어 캐시: composer 파일만 먼저
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
# 로컬 dev 캐시(sail/pail 등 dev 패키지 포함)를 지우고 운영 의존성 기준으로 다시 생성
RUN rm -f bootstrap/cache/*.php \
    && mkdir -p storage/framework/cache storage/framework/views storage/logs \
    && composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && rm -rf storage/* \
    && chown -R www-data:www-data bootstrap/cache

# 기본값(Coolify 환경변수로 덮어쓸 수 있음): DB 는 storage 볼륨 안, 로그는 컨테이너 로그로
ENV APP_ENV=production \
    APP_DEBUG=false \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/var/www/html/storage/database/database.sqlite \
    LOG_CHANNEL=stderr \
    AVATAR_ENGINE=fal

EXPOSE 80
# Laravel 기본 헬스 라우트. 첫 기동은 마이그레이션 때문에 몇 초 걸린다.
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fsS http://127.0.0.1/up >/dev/null || exit 1
ENTRYPOINT ["entrypoint"]
