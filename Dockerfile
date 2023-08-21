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

# Create system user to run Composer and Artisan Commands
# ARG user=devuser
# ARG uid=1000

# RUN useradd -G www-data,root -u $uid -d /home/$user $user
# RUN mkdir -p /home/$user/.composer && \
#     chown -R $user:$user /home/$user

# Apache config
# ENV APACHE_DOCUMENT_ROOT=/var/www/carpoolear
# RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
# RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY ./000-default.conf /etc/apache2/sites-available/
COPY ./default-ssl.conf /etc/apache2/sites-available/
COPY ./queue-worker.conf /etc/supervisor/conf.d/
RUN a2enmod rewrite && a2enmod headers
RUN a2enmod ssl
RUN a2ensite default-ssl

# WORKDIR /var/www/carpoolear/
# COPY ./composer.json .
# COPY ./composer.lock .
# TODO: remove the COPY . .
# COPY . .
# RUN composer install 
# RUN php artisan key:generate

CMD /usr/bin/supervisord & apachectl -D FOREGROUND
