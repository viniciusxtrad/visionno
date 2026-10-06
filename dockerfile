FROM php:8.2-apache

# ═══════════════════════════════════════════════════════════════
# 1) Habilita mod_rewrite (URLs amigáveis)
# ═══════════════════════════════════════════════════════════════
RUN a2enmod rewrite

# ═══════════════════════════════════════════════════════════════
# 2) Instala extensões necessárias (curl pro Redis)
# ═══════════════════════════════════════════════════════════════
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ═══════════════════════════════════════════════════════════════
# 3) Permite .htaccess (AllowOverride All)
# ═══════════════════════════════════════════════════════════════
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# ═══════════════════════════════════════════════════════════════
# 4) Configura o DocumentRoot
# ═══════════════════════════════════════════════════════════════
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# ═══════════════════════════════════════════════════════════════
# 5) Copia os arquivos
# ═══════════════════════════════════════════════════════════════
COPY . /var/www/html/

# ═══════════════════════════════════════════════════════════════
# 6) Dá permissão
# ═══════════════════════════════════════════════════════════════
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
