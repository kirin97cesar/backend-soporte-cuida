# Usa imagen PHP con Apache
FROM php:8.2-apache

# Instala extensiones necesarias (ajusta según tu proyecto)
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Habilita .htaccess (mod_rewrite)
RUN a2enmod rewrite

# Copia los archivos del proyecto al contenedor
COPY . /var/www/html/

# Establece permisos y carpeta pública
WORKDIR /var/www/html/public
