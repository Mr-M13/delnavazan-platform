#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

echo "This permanently deletes the disposable database, volumes and WordPress files."
read -r -p "Continue? (type YES) " answer
if [ "${answer}" != "YES" ]; then
  echo "Aborted."
  exit 1
fi

dzn_compose down --volumes --remove-orphans

# Remove the downloaded WordPress core + the plugin symlink so a rebuild starts
# from a genuinely empty state.
if [ -d "${DZN_WP_DIR}" ]; then
  rm -rf "${DZN_WP_DIR}"
  echo "Removed ${DZN_WP_DIR}"
fi

echo "Destroyed. Rebuild with: bin/up.sh && bin/run-all.sh"
