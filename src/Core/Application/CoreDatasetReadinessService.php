<?php
namespace Delnavazan\Platform\Core\Application;

use Delnavazan\Platform\Core\Support\Identifier;

/** Bounded operational seam: reads and records evidence; it never creates Core domain rows. */
final class CoreDatasetReadinessService {
    private const SCOPES = array('enrolments','teacher_assignments','canonical_lessons');
    private const TABLES = array('enrolments'=>'enrolments','teacher_assignments'=>'teacher_assignments','canonical_lessons'=>'lessons');
    public function reconcile(string $scope, int $expectedCount, string $expectedDigest): array {
        if (!in_array($scope,self::SCOPES,true) || $expectedCount < 0 || !preg_match('/^[a-f0-9]{64}$/D',$expectedDigest)) throw new \InvalidArgumentException('Bounded Core reconciliation input required');
        global $wpdb; $table=$wpdb->prefix.'dzn_'.self::TABLES[$scope]; $where=$scope==='canonical_lessons' ? " WHERE record_model='canonical_term_lesson_v1'" : ($scope==='teacher_assignments' ? " WHERE state IN ('assigned','replaced')" : '');
        $rows=$wpdb->get_results("SELECT id,uid,created_by,created_at FROM {$table}{$where} ORDER BY id ASC",ARRAY_A) ?: array(); $actualDigest=hash('sha256',wp_json_encode($rows,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        return array('scope'=>$scope,'expected_count'=>$expectedCount,'actual_count'=>count($rows),'expected_digest'=>$expectedDigest,'actual_digest'=>$actualDigest,'match'=>$expectedCount===count($rows)&&hash_equals($expectedDigest,$actualDigest));
    }
    public function recordOperation(string $operation,string $targetKind,?int $targetId,string $reason,string $evidenceChannel,string $evidenceReference,string $key,array $result): int {
        $actor=get_current_user_id(); if($actor<1) throw new \RuntimeException('Core readiness actor unavailable'); if($operation===''||$targetKind===''||$reason===''||$evidenceChannel===''||trim($evidenceReference)==='') throw new \InvalidArgumentException('Complete operator evidence required'); if(strlen(trim($key))<24) throw new \InvalidArgumentException('Operator idempotency key required');
        global $wpdb; $now=gmdate('Y-m-d H:i:s'); $payload=array('operation'=>$operation,'target_kind'=>$targetKind,'target_id'=>$targetId,'reason_code'=>$reason,'evidence_channel'=>$evidenceChannel,'result'=>$result); $ok=$wpdb->insert($wpdb->prefix.'dzn_core_dataset_operator_commands',array('uid'=>Identifier::uid(),'operation'=>$operation,'command_key_digest'=>hash_hmac('sha256',trim($key),wp_salt('dzn_core_dataset_readiness')),'command_payload_digest'=>hash('sha256',wp_json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)),'target_kind'=>$targetKind,'target_id'=>$targetId,'result_state'=>(string)($result['state']??'recorded'),'result_id'=>isset($result['id'])?(int)$result['id']:null,'evidence_channel'=>$evidenceChannel,'evidence_reference_digest'=>hash('sha256',$evidenceReference),'reason_code'=>$reason,'recorded_at'=>$now,'recorded_by'=>$actor,'created_at'=>$now,'created_by'=>$actor)); if($ok===false)throw new \RuntimeException('Core operator evidence persistence failed: '.$wpdb->last_error); return (int)$wpdb->insert_id;
    }
    public function recordCorrection(string $entityKind,int $entityId,string $priorDigest,string $correctedDigest,string $reason,string $channel,string $reference,string $note=''): int {
        $actor=get_current_user_id(); if($actor<1)throw new \RuntimeException('Core correction actor unavailable'); foreach(array($priorDigest,$correctedDigest) as $digest)if(!preg_match('/^[a-f0-9]{64}$/D',$digest))throw new \InvalidArgumentException('Correction digest required'); global $wpdb; $table=$wpdb->prefix.'dzn_core_dataset_corrections'; $sequence=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(MAX(correction_sequence),0)+1 FROM {$table} WHERE entity_kind=%s AND entity_id=%d",$entityKind,$entityId)); $now=gmdate('Y-m-d H:i:s'); $ok=$wpdb->insert($table,array('uid'=>Identifier::uid(),'entity_kind'=>$entityKind,'entity_id'=>$entityId,'correction_sequence'=>$sequence,'prior_digest'=>$priorDigest,'corrected_digest'=>$correctedDigest,'reason_code'=>$reason,'note_digest'=>$note===''?null:hash('sha256',$note),'evidence_channel'=>$channel,'evidence_reference_digest'=>hash('sha256',$reference),'evidence_at'=>$now,'recorded_at'=>$now,'recorded_by'=>$actor,'created_at'=>$now,'created_by'=>$actor)); if($ok===false)throw new \RuntimeException('Core correction evidence persistence failed: '.$wpdb->last_error); return(int)$wpdb->insert_id;
    }
}
