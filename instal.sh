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
# Ensure /api path resolves like in Docker image
ln -s backend/api api 2>/dev/null || true

open_firewall_port() {
  local port="$1"
  # Prefer UFW if present
  if command -v ufw >/dev/null 2>&1; then
    # Keep SSH accessible
    ufw allow OpenSSH >/dev/null 2>&1 || true
    ufw allow "$port"/tcp >/dev/null 2>&1 || true
    # Enable if disabled (non-interactive)
    ufw status | grep -qi inactive && echo y | ufw enable >/dev/null 2>&1 || true
    echo "Firewall: UFW allows TCP $port"
    return 0
  fi
  # Fallback to iptables
  if command -v iptables >/dev/null 2>&1; then
    # Avoid duplicate rule
    if ! iptables -C INPUT -p tcp --dport "$port" -j ACCEPT >/dev/null 2>&1; then
      iptables -A INPUT -p tcp --dport "$port" -j ACCEPT || true
    fi
    if command -v netfilter-persistent >/dev/null 2>&1; then
      netfilter-persistent save >/dev/null 2>&1 || true
    fi
    echo "Firewall: iptables allows TCP $port"
    return 0
  fi
  echo "Firewall: no UFW/iptables detected; ensure port $port is open"
}

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

  if [[ "$PORT" != "8080" ]]; then
    "${DOCKER_COMPOSE_CMD[@]}" down || true
    PORT_MAP="$PORT:80"
    # Build image ID and run with custom port mapping
    IMAGE_ID=$(docker build -q .)
    docker rm -f xeray-ui >/dev/null 2>&1 || true
    docker run -d --name xeray-ui -p "$PORT_MAP" \
      -v "$project_root_dir/logs:/var/www/html/logs" \
      -v "$project_root_dir/database:/var/www/html/database" \
      "$IMAGE_ID"
  else
    "${DOCKER_COMPOSE_CMD[@]}" up -d --build
  fi

  open_firewall_port "$PORT" || true
  echo "Xeray UI is running via Docker: http://$(hostname -I | awk '{print $1}'):$PORT"
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

open_firewall_port "$PORT" || true

# Run local server (bind to all interfaces)
echo "Starting PHP built-in server on http://0.0.0.0:$PORT ..."
echo "Press Ctrl+C to stop."
php -S "0.0.0.0:$PORT" -t .
