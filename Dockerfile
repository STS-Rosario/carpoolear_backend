# setup OS and timezone
FROM php:7.2-apache
LABEL Name=carpoolear_backend Version=0.0.1
ENV TZ=America/Argentina/Buenos_Aires
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone4
#install php git apache etc
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    supervisor 

RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    soap

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY ./000-default.conf /etc/apache2/sites-available/
COPY ./default-ssl.conf /etc/apache2/sites-available/
COPY ./queue-worker.conf /etc/supervisor/conf.d/
RUN a2enmod rewrite && a2enmod headers
RUN a2enmod ssl
RUN a2ensite default-ssl

CMD /usr/bin/supervisord & apachectl -D FOREGROUND
