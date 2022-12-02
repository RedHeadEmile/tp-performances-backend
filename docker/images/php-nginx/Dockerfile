FROM ubuntu:kinetic

ENV TZ="Europe/Paris"

# Install NGINX
RUN apt update && apt upgrade \
  && apt install -y tzdata \
  && apt install -y nginx \
  && apt install -y libnginx-mod-http-cache-purge;

# Install PHP
RUN apt update \
  && apt install -y php8.1-fpm php-dev pkg-php-tools \
  && apt install -y php8.1-cli php8.1-common php8.1-mysql php8.1-zip php8.1-gd php8.1-mbstring php8.1-curl php8.1-xml php8.1-bcmath \
  && pecl install redis \
  && echo "extension=redis.so" >> /etc/php/8.1/fpm/php.ini

# Install Git, Composer, Nano, ZIP \
RUN apt update \
    && apt install -y zip nano git curl sudo wget gpg \
    && curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php \
    && php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer;

# Setup websites folder
EXPOSE 80
EXPOSE 443

RUN mkdir /var/www/tp-performances \
    && touch /etc/nginx/sites-available/tp-performances \
    && ln -s /etc/nginx/sites-available/tp-performances /etc/nginx/sites-enabled \
    && unlink /etc/nginx/sites-enabled/default;

VOLUME /var/www/tp-performances
WORKDIR /var/www/tp-performances

STOPSIGNAL SIGQUIT

CMD ["/bin/bash", "-c", "php-fpm8.1 && chmod 777 /var/run/php/php8.1-fpm.sock && chmod 755 /usr/share/nginx/html/* && nginx -g 'daemon off;'"]


