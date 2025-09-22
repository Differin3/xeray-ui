#!/usr/bin/env bash

set -euo pipefail

# Usage: bash scripts/deploy.sh <GIT_REPO_URL> <TARGET_DIR> [BRANCH]

REPO_URL=${1:-}
TARGET_DIR=${2:-}
BRANCH=${3:-main}

if [[ -z "$REPO_URL" || -z "$TARGET_DIR" ]]; then
  echo "Usage: $0 <GIT_REPO_URL> <TARGET_DIR> [BRANCH]" >&2
  exit 1
fi

echo "[1/6] Cloning repo: $REPO_URL (branch: $BRANCH)"
if [[ -d "$TARGET_DIR/.git" ]]; then
  git -C "$TARGET_DIR" fetch --all --prune
  git -C "$TARGET_DIR" checkout "$BRANCH"
  git -C "$TARGET_DIR" reset --hard "origin/$BRANCH"
else
  mkdir -p "$TARGET_DIR"
  git clone --branch "$BRANCH" --depth 1 "$REPO_URL" "$TARGET_DIR"
fi

cd "$TARGET_DIR"

echo "[2/6] Creating runtime directories"
mkdir -p logs database

echo "[3/6] Permissions"
chmod -R u+rwX logs database || true

echo "[4/6] PHP extensions check (pdo_sqlite required)"
php -r 'exit(extension_loaded("pdo_sqlite")?0:1);' || {
  echo "ERROR: PHP extension pdo_sqlite is not enabled" >&2
  exit 2
}

echo "[5/6] DB bootstrap"
# Touch DB file; tables will be created by backend/core/database.php on first run
touch database/xeray.db

echo "[6/6] Start local server (Ctrl+C to stop)"
echo "Open: http://localhost:8080"
php -S localhost:8080 -t public | cat


