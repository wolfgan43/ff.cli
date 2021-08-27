#!/bin/bash

apt-get update

apt-get install -y mysqli
apt-get install -y redis
apt-get install -y curl
apt-get install -y openssl

# add-apt-repository 'deb https://repo.mongodb.org/apt/debian buster/mongodb-org/4.2 main'
# apt-get update
# apt-get install -y mongodb-org mongodb-org-database mongodb-org-server mongodb-org-shell mongodb-org-mongos mongodb-org-tools

apt-get install -y php8.0-xml php8.0-mbstring php8.0-gd php8.0-opcache php8.0-mysql php8.0-xdebug php8.0-zip php8.0-ssh2 php8.0-redis php8.0-soap php8.0-mongodb php8.0-mcrypt php8.0-gmp php8.0-curl php8.0-common php8.0-cli php8.0-bz2 php8.0-bcmath

a2enmod rewrite
a2enmod deflate
a2enmod expires
a2enmod headers
a2enmod http2
a2enmod mime
a2enmod php8.0

# nano /etc/apache2/sites-enabled/000-default.conf
#
#        DocumentRoot /var/www/html
#+        <Directory /var/www/html>
#+                Options Indexes FollowSymLinks
#+                AllowOverride All
#+                Require all granted
#+        </Directory>

systemctl restart apache2

