#!/usr/bin/env bash

echo "Installing php dev environment, please run it as root"

apt-get -y install  php5-cgi php5-cli php5-common php5-fpm php5-gd php5-curl php5-mysql php5-redis php5-mcrypt php5-xdebug

echo "Fix the mcrypt bug in ubuntu14.04"
php5enmod mcrypt

echo "Installing composer"
curl -sS https://getcomposer.org/installer | php
chmod +x composer.phar
mv composer.phar /usr/local/bin/composer

echo "Installing phpunit"
wget https://phar.phpunit.de/phpunit.phar
chmod +x phpunit.phar
mv phpunit.phar /usr/local/bin/phpunit