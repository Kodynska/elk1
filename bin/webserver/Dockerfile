FROM php:7.1-apache

RUN apt-get -y update --fix-missing

RUN apt-get -y install nano curl git unzip

RUN apt-get -y install mariadb-client
 # -y apt-transport-https
RUN docker-php-ext-install pdo_mysql
RUN docker-php-ext-install mysqli

RUN a2enmod rewrite

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

ADD entrypoint.sh /
ENTRYPOINT /entrypoint.sh
