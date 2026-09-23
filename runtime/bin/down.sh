#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"
echo "Stopping the disposable runtime (database and files are preserved)..."
dzn_compose stop
echo "Stopped. Use bin/up.sh to resume, or bin/destroy.sh to remove state."
