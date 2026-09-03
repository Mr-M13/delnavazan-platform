<?php
namespace Delnavazan\Platform\Core\Infrastructure\Repository;
final class EntityRepository {
	public function __construct(private string $table) {}
	public function active(int $id): ?object { global $wpdb; return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id=%d AND archived_at IS NULL", $id)); }
	public function create(array $data): int { global $wpdb; if(false===$wpdb->insert($this->table,$data)) throw new \RuntimeException($wpdb->last_error); return (int)$wpdb->insert_id; }
	public function set_reference(int $id,string $reference): void { global $wpdb; if(false===$wpdb->update($this->table,array('reference_code'=>$reference),array('id'=>$id))) throw new \RuntimeException($wpdb->last_error); }
}
