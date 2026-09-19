FROM dunglas/frankenphp:1-php8.5

WORKDIR /app

# ── System dependencies ──────────────────────────────────────────────
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        libicu-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libzip-dev \
        mariadb-client \
        unzip \
        zlib1g-dev \
    && rm -rf /var/lib/apt/lists/*

# ── PHP extensions ───────────────────────────────────────────────────
RUN install-php-extensions \
    pdo_mysql \
    mysqli \
    gd \
    intl \
    zip \
    mongodb \
    redis \
    opcache

# ── Composer ─────────────────────────────────────────────────────────
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && php -r "unlink('composer-setup.php');"

# ── PHP configuration ────────────────────────────────────────────────
RUN printf "%s\n" \
    "upload_max_filesize=10M" \
    "post_max_size=10M" \
    "memory_limit=256M" \
    > /usr/local/etc/php/conf.d/app.ini

# ── Install composer dependencies (cached layer) ─────────────────────
# Copy composer files first for Docker layer caching
COPY composer.json composer.json
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# ── Copy application code ────────────────────────────────────────────
COPY . /app

# ── Re-run composer post-scripts & dump-autoload ─────────────────────
RUN composer dump-autoload --optimize --no-dev

# ── Writable directories ─────────────────────────────────────────────
RUN mkdir -p tmp uploads templates_c \
    && chown -R www-data:www-data tmp uploads templates_c

# ── Entrypoint ───────────────────────────────────────────────────────
COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# ── Caddy config ─────────────────────────────────────────────────────
COPY Caddyfile /etc/caddy/Caddyfile

EXPOSE 80 443

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

ENTRYPOINT ["entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
