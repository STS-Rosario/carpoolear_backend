# setup OS and timezone
FROM ubuntu:18.04
LABEL Name=carpoolear_backend Version=0.0.1
ENV TZ=America/Argentina/Buenos_Aires
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone4
#install php git apache etc
RUN apt-get update -y &&\
    apt-get install -y php7.2 \
    curl \
    php-curl \
    php7.2-mysql \
    php-gd \
    php-cli \
    php-zip \
    php-mbstring \
    php-xml \
    unzip \
    git \
    apache2 \
    supervisor 

#install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
#setup apache
COPY ./000-default.conf /etc/apache2/sites-available/
COPY ./default-ssl.conf /etc/apache2/sites-available/
COPY ./queue-worker.conf /etc/supervisor/conf.d/
RUN a2enmod rewrite && a2enmod headers
RUN a2enmod ssl
RUN a2ensite default-ssl
# COPY . /var/www/carpoolear/
# RUN chmod -R ugo+rw /var/www/carpoolear/storage/*
WORKDIR /var/www/carpoolear/
EXPOSE 80
CMD  /usr/bin/supervisord & apachectl -D FOREGROUND
