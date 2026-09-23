#!/usr/bin/env bash
# Shared helpers for the Delnavazan Platform disposable runtime.
# Source this file (not execute) from the other runtime/bin scripts.

set -euo pipefail

# --- Locate directories -----------------------------------------------------
DZN_BIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DZN_RUNTIME_DIR="$(cd "${DZN_BIN_DIR}/.." && pwd)"
DZN_REPO_ROOT="$(cd "${DZN_RUNTIME_DIR}/.." && pwd)"

# --- Load .env (optional) ---------------------------------------------------
if [ -f "${DZN_RUNTIME_DIR}/.env" ]; then
  # shellcheck disable=SC1091
  set -a; . "${DZN_RUNTIME_DIR}/.env"; set +a
fi

# --- Normalised configuration ----------------------------------------------
DZN_COMPOSE_PROJECT="${COMPOSE_PROJECT_NAME:-dzn-platform-runtime}"
DZN_NETWORK="${DZN_COMPOSE_PROJECT}_dzn"

# Resolve the WordPress directory to an absolute host path (the concurrency
# runner must mount this exact directory into its worker containers).
DZN_WP_DIR_REL="${DZN_WP_DIR:-./wordpress}"
case "${DZN_WP_DIR_REL}" in
  /*) DZN_WP_DIR="${DZN_WP_DIR_REL}" ;;
  *)  DZN_WP_DIR="${DZN_RUNTIME_DIR}/${DZN_WP_DIR_REL}" ;;
esac

DZN_DB_IMAGE="${DZN_DB_IMAGE:-mariadb:11.4}"
DZN_WP_IMAGE="${DZN_WP_IMAGE:-wordpress:php8.3-apache}"
DZN_CLI_IMAGE="${DZN_CLI_IMAGE:-wordpress:cli-php8.3}"
DZN_MAILPIT_IMAGE="${DZN_MAILPIT_IMAGE:-axllent/mailpit:latest}"

DZN_WP_PORT="${DZN_WP_PORT:-8080}"
DZN_WP_URL="${DZN_WP_URL:-http://localhost:8080}"
DZN_WP_TITLE="${DZN_WP_TITLE:-Delnavazan Platform (disposable)}"
DZN_WP_ADMIN_USER="${DZN_WP_ADMIN_USER:-admin}"
DZN_WP_ADMIN_PASSWORD="${DZN_WP_ADMIN_PASSWORD:-admin}"
DZN_WP_ADMIN_EMAIL="${DZN_WP_ADMIN_EMAIL:-admin@example.invalid}"

DZN_DB_NAME="${DZN_DB_NAME:-wordpress}"
DZN_DB_USER="${DZN_DB_USER:-wordpress}"
DZN_DB_PASSWORD="${DZN_DB_PASSWORD:-wordpress}"
DZN_DB_ROOT_PASSWORD="${DZN_DB_ROOT_PASSWORD:-root}"
DZN_WP_ENVIRONMENT_TYPE="${DZN_WP_ENVIRONMENT_TYPE:-local}"

export DZN_REPO_ROOT DZN_RUNTIME_DIR DZN_WP_DIR DZN_COMPOSE_PROJECT DZN_NETWORK

# --- Command wrappers -------------------------------------------------------
dzn_compose() {
  docker compose \
    --project-directory "${DZN_RUNTIME_DIR}" \
    --env-file "${DZN_RUNTIME_DIR}/.env" \
    -f "${DZN_RUNTIME_DIR}/compose.yaml" \
    "$@"
}

# Run a one-off WP-CLI container on the shared network. This is the exact
# invocation pattern the existing concurrency runners use, so every scripted
# WP-CLI command behaves the same whether it is a test, a migration or an
# install step.
#
# dzn_wp [wp-arg ...]
dzn_wp() {
  docker run --rm --network "${DZN_NETWORK}" \
    -u 0 \
    -e "WP_ENVIRONMENT_TYPE=${DZN_WP_ENVIRONMENT_TYPE}" \
    -e "WORDPRESS_DB_HOST=db" \
    -e "WORDPRESS_DB_NAME=${DZN_DB_NAME}" \
    -e "WORDPRESS_DB_USER=${DZN_DB_USER}" \
    -e "WORDPRESS_DB_PASSWORD=${DZN_DB_PASSWORD}" \
    -v "${DZN_WP_DIR}:/var/www/html" \
    -v "${DZN_REPO_ROOT}:${DZN_REPO_ROOT}" \
    --entrypoint php \
    "${DZN_CLI_IMAGE}" \
    -d memory_limit="${DZN_PHP_MEMORY_LIMIT:-512M}" /usr/local/bin/wp --path=/var/www/html --allow-root "$@"
}

# dzn_wp_env KEY=VALUE [KEY=VALUE ...] -- [wp-arg ...]
dzn_wp_env() {
  local -a envs=()
  while [ "$#" -gt 0 ]; do
    case "$1" in
      --) shift; break ;;
      *) envs+=( "$1" ); shift ;;
    esac
  done
  local -a args=()
  for e in "${envs[@]}"; do args+=( -e "$e" ); done
  docker run --rm --network "${DZN_NETWORK}" \
    -u 0 \
    -e "WP_ENVIRONMENT_TYPE=${DZN_WP_ENVIRONMENT_TYPE}" \
    -e "WORDPRESS_DB_HOST=db" \
    -e "WORDPRESS_DB_NAME=${DZN_DB_NAME}" \
    -e "WORDPRESS_DB_USER=${DZN_DB_USER}" \
    -e "WORDPRESS_DB_PASSWORD=${DZN_DB_PASSWORD}" \
    "${args[@]}" \
    -v "${DZN_WP_DIR}:/var/www/html" \
    -v "${DZN_REPO_ROOT}:${DZN_REPO_ROOT}" \
    --entrypoint php \
    "${DZN_CLI_IMAGE}" \
    -d memory_limit="${DZN_PHP_MEMORY_LIMIT:-512M}" /usr/local/bin/wp --path=/var/www/html --allow-root "$@"
}

dzn_status() {
  printf '%-24s %s\n' "repo" "${DZN_REPO_ROOT}"
  printf '%-24s %s\n' "runtime" "${DZN_RUNTIME_DIR}"
  printf '%-24s %s\n' "wordpress dir" "${DZN_WP_DIR}"
  printf '%-24s %s\n' "project" "${DZN_COMPOSE_PROJECT}"
  printf '%-24s %s\n' "network" "${DZN_NETWORK}"
  printf '%-24s %s\n' "db image" "${DZN_DB_IMAGE}"
  printf '%-24s %s\n' "wp image" "${DZN_WP_IMAGE}"
  printf '%-24s %s\n' "cli image" "${DZN_CLI_IMAGE}"
  printf '%-24s %s\n' "wp url" "${DZN_WP_URL}"
}

require_env_file() {
  if [ ! -f "${DZN_RUNTIME_DIR}/.env" ]; then
    echo "error: runtime/.env is missing." >&2
    echo "       cp runtime/.env.example runtime/.env and adjust DZN_REPO_ROOT if needed." >&2
    exit 1
  fi
}
