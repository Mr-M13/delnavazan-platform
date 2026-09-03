<?php
namespace Delnavazan\Platform\Core\Infrastructure\Repository;
final class OperationalExceptionRepository extends BaseRepository { public function __construct(){ global $wpdb; $this->table=$wpdb->prefix . 'dzn_operational_exceptions'; } }
