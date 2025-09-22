# Xeray UI - Docker deployment (PHP 8.2 + Apache)
# Serves from /var/www/html (project root) and enables SQLite

FROM php:8.2-apache

# Install required packages and PHP extensions
RUN apt-get update \
	&& apt-get install -y --no-install-recommends \
		git \
		unzip \
		curl \
		libsqlite3-0 \
		libsqlite3-dev \
	&& rm -rf /var/lib/apt/lists/* \
	&& docker-php-ext-install pdo pdo_sqlite

# Enable useful modules
RUN a2enmod rewrite

# Create runtime dirs and permissions
RUN mkdir -p /var/www/html/logs /var/www/html/database \
	&& chown -R www-data:www-data /var/www/html \
	&& chmod -R 775 /var/www/html/logs /var/www/html/database

# Copy source
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html

# Symlink /api to backend/api so frontend requests to /api/* resolve
RUN ln -s /var/www/html/backend/api /var/www/html/api || true

# Copy entrypoint
COPY scripts/xeray-entrypoint.sh /usr/local/bin/xeray-entrypoint
RUN chmod +x /usr/local/bin/xeray-entrypoint

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD curl -fsS http://localhost/ || exit 1

ENTRYPOINT ["/usr/local/bin/xeray-entrypoint"]
