#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

echo "Resetting database and WordPress state (containers stay up)..."
dzn_compose down --volumes --remove-orphans

if [ -d "${DZN_WP_DIR}" ]; then
  rm -rf "${DZN_WP_DIR}"
  echo "Removed ${DZN_WP_DIR}"
fi

dzn_compose up -d --wait db
dzn_compose up -d wordpress mailpit cli
echo "Reset complete. Run: bin/install-wordpress.sh && bin/install-plugin.sh"
