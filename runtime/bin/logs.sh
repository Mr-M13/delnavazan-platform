#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"
exec dzn_compose logs -f --tail=100 "${@:-wordpress db cli mailpit}"
