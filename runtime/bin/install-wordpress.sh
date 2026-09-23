#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"
require_env_file

if [ ! -f "${DZN_WP_DIR}/wp-load.php" ]; then
  echo "error: WordPress core not present at ${DZN_WP_DIR}." >&2
  echo "       Run bin/up.sh first so the wordpress image can populate core." >&2
  exit 1
fi

if dzn_wp core is-installed >/dev/null 2>&1; then
  echo "WordPress is already installed."
else
  echo "Running wp core install..."
  dzn_wp core install \
    --url="${DZN_WP_URL}" \
    --title="${DZN_WP_TITLE}" \
    --admin_user="${DZN_WP_ADMIN_USER}" \
    --admin_password="${DZN_WP_ADMIN_PASSWORD}" \
    --admin_email="${DZN_WP_ADMIN_EMAIL}" \
    --skip-email
fi

echo "Verifying environment type..."
dzn_wp eval 'echo wp_get_environment_type() . "\n";'
echo "WordPress install complete."
