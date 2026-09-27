#!/usr/bin/env bash
# Immutable rehearsal: a Schema-30 database built from 86d5760 is upgraded in place
# by a separate Schema-31 worktree. The shared checkout is never checked out or linked.
set -euo pipefail
source "$(dirname "$BASH_SOURCE")/common.sh"
DZN_SCHEMA30_REF="${DZN_SCHEMA30_REF:-86d57606cabcddba15d076edfe14fb4e7257e60f}"
dzn_require_cached_images; dzn_candidate; dzn_prepare_worktree "$DZN_BASE30_WORKTREE" "$DZN_SCHEMA30_REF"
dzn_assert_runtime_paths
dzn_compose down --volumes --remove-orphans || true
dzn_remove_disposable_path "$DZN_DB_DIR"; dzn_remove_disposable_path "$DZN_WP_DIR"; mkdir -p "$DZN_DB_DIR" "$DZN_WP_DIR"
dzn_compose up --pull never -d --wait db
dzn_compose up --pull never -d wordpress
DZN_PLUGIN_SOURCE="$DZN_BASE30_WORKTREE"; export DZN_PLUGIN_SOURCE
dzn_assert_runtime_paths
for _ in $(seq 1 30); do [ -f "$DZN_WP_DIR/wp-load.php" ] && break; sleep 1; done
[ -f "$DZN_WP_DIR/wp-load.php" ] || { echo 'error: WordPress core was not supplied by the cached image.' >&2; exit 1; }
dzn_wp core install --url="$DZN_WP_URL" --title='Schema 30 rehearsal' --admin_user=admin --admin_password=local-only-not-a-secret --admin_email=admin@example.invalid --skip-email
plugin="$DZN_WP_DIR/wp-content/plugins/delnavazan-platform"; mkdir -p "$(dirname "$plugin")"; ln -s "$DZN_BASE30_WORKTREE" "$plugin"
dzn_wp plugin activate delnavazan-platform
dzn_wp eval 'if ((string)get_option("dzn_platform_schema_version") !== "30") throw new RuntimeException("immutable base did not install Schema 30"); update_option("dzn_runtime_rehearsal_preserved_option","preserve-me",false);'
snapshot='global $wpdb;$tables=$wpdb->get_col("SHOW TABLES");sort($tables,SORT_STRING);$out=array();foreach($tables as $t){$rows=$wpdb->get_results("SELECT * FROM ".$t,ARRAY_A);if(substr($t,-8)==="options")$rows=array_values(array_filter($rows,static fn($r)=>!in_array($r["option_name"],array("dzn_platform_schema_version","dzn_platform_completed_migrations"),true)));$create=$wpdb->get_row("SHOW CREATE TABLE ".$t,ARRAY_N);$out[$t]=array("schema"=>hash("sha256",(string)$create[1]),"data"=>hash("sha256",serialize($rows)));}echo wp_json_encode($out);'
dzn_wp eval "$snapshot" >"$DZN_RUNTIME_STATE_DIR/schema30-before.json"
rm -f "$plugin"; ln -s "$DZN_PLUGIN_WORKTREE" "$plugin"
DZN_PLUGIN_SOURCE="$DZN_PLUGIN_WORKTREE"; export DZN_PLUGIN_SOURCE
dzn_assert_runtime_paths
dzn_wp eval '\Delnavazan\Platform\Core\Infrastructure\Migration\Migrator::maybe_upgrade();'
dzn_wp eval "$snapshot" >"$DZN_RUNTIME_STATE_DIR/schema31-after.json"
dzn_php -r '$before=json_decode(file_get_contents("/state/schema30-before.json"),true);$after=json_decode(file_get_contents("/state/schema31-after.json"),true);if(!is_array($before)||!is_array($after))throw new RuntimeException("invalid snapshot");$added=array_values(array_diff(array_keys($after),array_keys($before)));sort($added);$expected=array("wp_dzn_portal_access_denials","wp_dzn_portal_lesson_capability_roots","wp_dzn_portal_public_action_events","wp_dzn_portal_public_capabilities","wp_dzn_portal_public_capability_commands","wp_dzn_portal_public_capability_events");if($added!==$expected)throw new RuntimeException("added tables were not exactly the six portal tables: ".json_encode($added));foreach($before as $table=>$shape)if(!isset($after[$table])||$after[$table]!==$shape)throw new RuntimeException("Schema-30 data or table changed: ".$table);echo "schema30_to_31_preservation_ok\\n";'
"$(dirname "$BASH_SOURCE")/verify-schema31.sh"
echo 'Schema 30 (86d5760) -> 31 rehearsal: PASS'
