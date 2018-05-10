FROM php:7.1-cli-alpine 
RUN apk upgrade --update && apk add --update libmcrypt-dev openssl git zip unzip
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN docker-php-ext-install pdo pdo_mysql mbstring mcrypt mysqli 
 
RUN apk add --update --no-cache autoconf g++ imagemagick-dev libtool make pcre-dev \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && apk del autoconf g++ libtool make pcre-dev

WORKDIR /app

COPY composer.json composer.lock /app/
RUN composer install --no-autoloader --no-scripts

COPY . /app
RUN composer dumpautoload

ENV SERVER_PORT=8080

CMD php artisan serve --host=0.0.0.0 --port=$SERVER_PORT
EXPOSE 8080

