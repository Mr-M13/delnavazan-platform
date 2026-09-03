<?php
namespace Delnavazan\Platform\Core\Application;

use Delnavazan\Platform\Core\Infrastructure\Repository\CoreStatusRepository;

final class DiagnosticsService {
    public function status(): array {
        if (!current_user_can('dzn_view_diagnostics')) throw new \RuntimeException('Unauthorized');
        $snapshot = (new CoreStatusRepository())->snapshot();
        return [
            'platform_version' => DZN_PLATFORM_VERSION,
            'schema_version' => get_option('dzn_platform_schema_version', 'not installed'),
            'migration_state' => (array) get_option('dzn_platform_completed_migrations', []),
            'tables' => $snapshot['tables'],
            'exception_counts' => $snapshot['exception_counts'],
            'healthy' => !in_array(false, array_column($snapshot['tables'], 'exists'), true),
        ];
    }
}
