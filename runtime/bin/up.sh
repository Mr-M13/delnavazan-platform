#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"
require_env_file

dzn_status
echo
echo "Pulling images (first run may download; later runs use the local cache)..."
dzn_compose pull
echo
echo "Starting db + wordpress + cli + mailpit..."
dzn_compose up -d --wait db
dzn_compose up -d wordpress mailpit cli

echo
echo "Stack is up. Next steps:"
echo "  bin/install-wordpress.sh   # wp core install (creates admin + tables)"
echo "  bin/install-plugin.sh      # symlink + activate the Platform plugin (runs migrations)"
echo "  bin/run-all.sh             # end-to-end: fresh install -> schema 25 -> tests"
