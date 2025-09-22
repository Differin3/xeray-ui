#!/usr/bin/env bash
set -euo pipefail

# Xeray UI - Installation/Bootstrap script
# Usage:
#   bash instal.sh [--docker] [--no-docker] [--port 8080]
# Defaults: try docker if available, fallback to PHP built-in server; port 8080

PORT=8080
USE_DOCKER=auto

while [[ $# -gt 0 ]]; do
  case "$1" in
    --docker)
      USE_DOCKER=yes
      shift
      ;;
    --no-docker)
      USE_DOCKER=no
      shift
      ;;
    --port)
      PORT="${2:-8080}"
      shift 2
      ;;
    *)
      echo "Unknown option: $1" >&2
      exit 1
      ;;
  esac
done

project_root_dir="$(cd "$(dirname "$0")" && pwd)"
cd "$project_root_dir"

mkdir -p logs database

if [[ "$USE_DOCKER" == "auto" ]]; then
  if command -v docker >/dev/null 2>&1; then
    USE_DOCKER=yes
  else
    USE_DOCKER=no
  fi
fi

if [[ "$USE_DOCKER" == "yes" ]]; then
  if ! command -v docker >/dev/null 2>&1; then
    echo "Docker not found. Install Docker or run with --no-docker" >&2
    exit 1
  fi
  # Prefer docker compose v2 syntax
  if docker compose version >/dev/null 2>&1; then
    DOCKER_COMPOSE_CMD=(docker compose)
  elif command -v docker-compose >/dev/null 2>&1; then
    DOCKER_COMPOSE_CMD=(docker-compose)
  else
    echo "docker compose not available. Install Docker Compose or use --no-docker" >&2
    exit 1
  fi

  # If user set a non-default port, override via compose
  if [[ "$PORT" != "8080" ]]; then
    "${DOCKER_COMPOSE_CMD[@]}" down || true
    PORT_ENV="PORT_OVERRIDE=$PORT"
    # Run with inline override of port mapping
    PORT_MAP="$PORT:80"
    ${DOCKER_COMPOSE_CMD[@]} up -d --build
    # Then adjust port mapping if needed by recreating service
    docker stop xeray-ui >/dev/null 2>&1 || true
    docker rm xeray-ui >/dev/null 2>&1 || true
    docker run -d --name xeray-ui -p "$PORT_MAP" \
      -v "$project_root_dir/logs:/var/www/html/logs" \
      -v "$project_root_dir/database:/var/www/html/database" \
      $(docker build -q .)
  else
    "${DOCKER_COMPOSE_CMD[@]}" up -d --build
  fi

  echo "Xeray UI is running via Docker: http://localhost:$PORT"
  exit 0
fi

# Fallback: run with PHP built-in server
if ! command -v php >/dev/null 2>&1; then
  echo "PHP is not installed. Install PHP 8+ or run with --docker" >&2
  exit 1
fi

# Basic dependency check for SQLite
if ! php -r 'exit(function_exists("pdo_sqlite")?0:1);'; then
  echo "PHP extension pdo_sqlite is missing. Please enable/install it." >&2
  exit 1
fi

# Run local server
echo "Starting PHP built-in server on http://localhost:$PORT ..."
echo "Press Ctrl+C to stop."
php -S "localhost:$PORT" -t public
