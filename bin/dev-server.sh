#!/usr/bin/env bash
# Serveur de développement : http://localhost:8080
# Usage : bin/dev-server.sh [port]
set -euo pipefail
cd "$(dirname "$0")/.."
PORT="${1:-8080}"
exec php -d upload_max_filesize=64M -d post_max_size=70M -S "127.0.0.1:${PORT}" -t public public/index.php
