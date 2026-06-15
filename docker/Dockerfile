# 프로덕션 PHP-FPM 런타임. 코드는 빌드에 넣지 않고 실행 시 바인드마운트한다
# (deploy.sh 에서 composer install 수행). 이 이미지는 순수 런타임 + 확장 모듈.
FROM php:8.5-fpm

# 확장 모듈 설치 도우미
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/bin/
RUN install-php-extensions \
        pdo_sqlite \
        gd \
        zip \
        intl \
        bcmath \
        opcache \
        pcntl

# composer (deploy 시 사용)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 프로덕션 php.ini 튜닝
COPY docker/php.ini /usr/local/etc/php/conf.d/zzz-app.ini

WORKDIR /var/www/html
