#!/usr/bin/env bash
set -e

APP_DIR=/var/www/html
LOGS_DIR="$APP_DIR/logs"
DB_DIR="$APP_DIR/database"

mkdir -p "$LOGS_DIR" "$DB_DIR"
chown -R www-data:www-data "$LOGS_DIR" "$DB_DIR" || true
chmod -R 775 "$LOGS_DIR" "$DB_DIR" || true

# Create api symlink if missing
[ -e "$APP_DIR/api" ] || ln -s "$APP_DIR/backend/api" "$APP_DIR/api" || true

exec apache2-foreground
