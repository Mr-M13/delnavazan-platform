#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"
require_env_file

PLUGIN_DIR="${DZN_WP_DIR}/wp-content/plugins/delnavazan-platform"

if [ ! -f "${DZN_WP_DIR}/wp-load.php" ]; then
  echo "error: WordPress core not present. Run bin/up.sh && bin/install-wordpress.sh first." >&2
  exit 1
fi

# Symlink the repository root into wp-content/plugins so the plugin is loaded
# from the live checkout. The concurrency runner mounts the repository at the
# same absolute path, so an absolute symlink resolves inside those containers.
if [ ! -e "${PLUGIN_DIR}" ]; then
  ln -s "${DZN_REPO_ROOT}" "${PLUGIN_DIR}"
  echo "Linked plugin -> ${DZN_REPO_ROOT}"
else
  echo "Plugin already linked: ${PLUGIN_DIR}"
fi

echo "Activating plugin (this runs migrations 001..025 on a fresh database)..."
dzn_wp plugin activate delnavazan-platform --path=/var/www/html

echo "Verifying schema identity..."
dzn_wp eval 'echo "schema=" . get_option("dzn_platform_schema_version") . "\n";'
echo "Plugin install/activation complete."
