FROM php:8.2-apache

# Habilita mod_rewrite (URLs amigáveis)
RUN a2enmod rewrite

# Instala extensões necessárias (curl pro Redis)
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Permite .htaccess (AllowOverride All)
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Configura ServerName
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Copia os arquivos
COPY . /var/www/html/

# Dá permissão
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
