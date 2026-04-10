FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN a2enmod rewrite

# 👇 ESTA PARTE ES LA CLAVE
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf \
 && echo "KeepAlive On" >> /etc/apache2/apache2.conf \
 && echo "MaxKeepAliveRequests 100" >> /etc/apache2/apache2.conf \
 && echo "KeepAliveTimeout 5" >> /etc/apache2/apache2.conf

# 👇 Evita que Apache se apague con señales de Render
RUN sed -i 's/^Listen 80$/Listen 0.0.0.0:80/' /etc/apache2/ports.conf

COPY . /var/www/html/
WORKDIR /var/www/html/public

RUN echo "error_reporting = E_ALL & ~E_WARNING" > /usr/local/etc/php/conf.d/no-warnings.ini \
 && echo "display_errors = Off" >> /usr/local/etc/php/conf.d/no-warnings.ini

# 👇 ESTA LÍNEA REEMPLAZA EL ENTRYPOINT DEFECTUOSO
CMD ["apachectl", "-D", "FOREGROUND"]