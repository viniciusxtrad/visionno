FROM php:8.2-apache

# Habilita mod_rewrite (pro .htaccess)
RUN a2enmod rewrite

# Instala curl (pro Redis)
RUN apt-get update && apt-get install -y libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && apt-get clean

# Permite .htaccess
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Copia os arquivos
COPY . /var/www/html/

EXPOSE 80

CMD ["apache2-foreground"]
