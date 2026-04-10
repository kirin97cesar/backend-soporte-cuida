FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN a2enmod rewrite

# 🔥 BORRAR el vhost que trae la imagen
RUN rm /etc/apache2/sites-enabled/000-default.conf

# 🔥 CREAR un vhost limpio apuntando a /public
RUN printf '%s\n' \
'<VirtualHost *:80>' \
'    ServerName localhost' \
'    DocumentRoot /var/www/html/public' \
'    <Directory /var/www/html/public>' \
'        AllowOverride All' \
'        Require all granted' \
'    </Directory>' \
'</VirtualHost>' \
> /etc/apache2/sites-available/000-default.conf

RUN a2ensite 000-default.conf

COPY . /var/www/html/

RUN echo "error_reporting = E_ALL & ~E_WARNING" > /usr/local/etc/php/conf.d/no-warnings.ini \
 && echo "display_errors = Off" >> /usr/local/etc/php/conf.d/no-warnings.ini