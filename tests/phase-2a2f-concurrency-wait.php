<?php
/** Proves the contender is in an InnoDB row-lock wait before holder release. */
if (
    getenv('DZN_PHASE_2A2F_RUNTIME_TEST') !== 'isolated'
    || !defined('WP_CLI')
    || !WP_CLI
    || !in_array(wp_get_environment_type(), array('local', 'development'), true)
) {
    fwrite(STDERR, "Phase 2A.2-F lock-wait probe refused.\n");
    exit(1);
}

global $wpdb;
$gate = (string) getenv('DZN_PHASE_2A2F_GATE_DIR');
if (!is_dir($gate) || !is_writable($gate)) {
    throw new RuntimeException('Phase 2A.2-F lock-wait gate unavailable');
}

$wpdb->suppress_errors(true);
for ($attempt = 0; $attempt < 600; $attempt++) {
    $waits = $wpdb->get_var(
        "SELECT COUNT(*)
         FROM performance_schema.data_lock_waits w
         INNER JOIN performance_schema.data_locks l
           ON l.ENGINE_LOCK_ID=w.REQUESTING_ENGINE_LOCK_ID
         WHERE l.OBJECT_SCHEMA=DATABASE()"
    );
    if ($waits === null && $wpdb->last_error !== '') {
        throw new RuntimeException(
            'Lock-wait probe unavailable; grant the disposable DB user SELECT on performance_schema.data_lock_waits and data_locks'
        );
    }
    if ((int) $waits > 0) {
        file_put_contents($gate . '/w2.blocked', (string) $waits . "\n");
        echo "Phase 2A.2-F contender row-lock wait observed\n";
        exit(0);
    }
    usleep(100000);
}
throw new RuntimeException('Contender did not enter an observable InnoDB row-lock wait');
