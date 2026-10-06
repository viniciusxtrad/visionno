FROM php:8.2-apache

# ═══════════════════════════════════════════════════════════════
# 1) Habilita mod_rewrite (URLs amigáveis)
# ═══════════════════════════════════════════════════════════════
RUN a2enmod rewrite

# ═══════════════════════════════════════════════════════════════
# 2) Instala extensões necessárias + unzip + git (pro composer)
# ═══════════════════════════════════════════════════════════════
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    unzip \
    git \
    && docker-php-ext-install curl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ═══════════════════════════════════════════════════════════════
# 3) Instala o Composer
# ═══════════════════════════════════════════════════════════════
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ═══════════════════════════════════════════════════════════════
# 4) Permite .htaccess
# ═══════════════════════════════════════════════════════════════
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# ═══════════════════════════════════════════════════════════════
# 5) Configura ServerName
# ═══════════════════════════════════════════════════════════════
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# ═══════════════════════════════════════════════════════════════
# 6) Copia os arquivos
# ═══════════════════════════════════════════════════════════════
COPY . /var/www/html/

# ═══════════════════════════════════════════════════════════════
# 7) Roda composer install (cria o vendor/)
# ═══════════════════════════════════════════════════════════════
WORKDIR /var/www/html
RUN composer install --no-dev --optimize-autoloader --no-interaction

# ═══════════════════════════════════════════════════════════════
# 8) Dá permissão
# ═══════════════════════════════════════════════════════════════
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
