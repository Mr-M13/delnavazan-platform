<?php
namespace Delnavazan\Platform\Core\Infrastructure\Repository;
final class TermRepository extends BaseRepository { public function __construct(){ global $wpdb; $this->table=$wpdb->prefix . 'dzn_terms'; } public function sequenceExists(int $enrolment,int $sequence):bool{global $wpdb;return (bool)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->table} WHERE enrolment_id=%d AND sequence_number=%d",$enrolment,$sequence));} }
