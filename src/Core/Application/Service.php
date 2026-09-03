<?php
namespace Delnavazan\Platform\Core\Application;
use Delnavazan\Platform\Core\Support\Identifier;
final class Service {
	private const STATES = array( 'teacher'=>array('active','inactive','archived'), 'student'=>array('active','inactive','archived'), 'instrument'=>array('active','inactive','archived'), 'course'=>array('active','inactive','archived'), 'enrolment'=>array('draft','active','paused','ending','completed','cancelled','archived'), 'term'=>array('draft','awaiting_payment','active','completed','cancelled','archived'), 'lesson'=>array('draft','scheduled','cancelled','completed','archived') );
	public static function create( string $entity, array $data ): int {
		self::allow( 'dzn_manage_' . ( $entity === 'instrument' ? 'courses' : $entity . 's' ) );
		self::validate( $entity, $data ); global $wpdb; $table = $wpdb->prefix . 'dzn_' . $entity . 's'; $prefix = array('teacher'=>'DZN-TCH-','student'=>'DZN-STU-','instrument'=>'DZN-INS-','course'=>'DZN-CRS-','enrolment'=>'DZN-ENR-','term'=>'DZN-TRM-','lesson'=>'DZN-LSN-')[ $entity ];
		$now = gmdate('Y-m-d H:i:s'); $data = array_map( 'sanitize_text_field', $data ); $data['uid'] = Identifier::uid(); $data['created_at']=$now; $data['updated_at']=$now; $data['created_by']=get_current_user_id() ?: null; $data['updated_by']=$data['created_by'];
		$wpdb->query('START TRANSACTION');
		try { if ( false === $wpdb->insert( $table, $data ) ) { throw new \RuntimeException( $wpdb->last_error ); } $id=(int)$wpdb->insert_id; if ( false === $wpdb->update($table,array('reference_code'=>Identifier::reference($prefix,$id)),array('id'=>$id)) ) throw new \RuntimeException($wpdb->last_error); $wpdb->query('COMMIT'); return $id; }
		catch ( \Throwable $e ) { $wpdb->query('ROLLBACK'); throw $e; }
	}
	public static function validate( string $entity, array $d ): void {
		if ( ! in_array( $d['status'] ?? '', self::STATES[$entity] ?? array(), true ) ) throw new \InvalidArgumentException('Invalid lifecycle state.');
		if ( isset($d['timezone']) && $d['timezone'] && ! in_array($d['timezone'], timezone_identifiers_list(), true) ) throw new \InvalidArgumentException('Invalid IANA timezone.');
		if ( $entity === 'lesson' ) { $type=$d['lesson_type']??''; if(!in_array($type,array('introductory','standard','replacement'),true)) throw new \InvalidArgumentException('Invalid lesson type.'); if($type==='standard' && (empty($d['enrolment_id'])||empty($d['term_id']))) throw new \InvalidArgumentException('Standard Lesson requires Enrolment and Term.'); if($type==='replacement' && (empty($d['enrolment_id'])||empty($d['term_id'])||empty($d['replacement_for_lesson_id']))) throw new \InvalidArgumentException('Replacement Lesson requires relationship and original.'); if(($d['replacement_for_lesson_id']??0)==($d['id']??null)) throw new \InvalidArgumentException('Lesson cannot replace itself.'); }
	}
	private static function allow(string $cap): void { if(!is_user_logged_in() || !current_user_can($cap)) throw new \RuntimeException('Unauthorized.'); }
}
