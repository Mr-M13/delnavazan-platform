<?php
namespace Delnavazan\Platform\Core\Application\Finance;

/**
 * Shared fail-closed authority, serialisation and evidence handling for Phase 2A.2-U commands (§15).
 *
 * Two serialisation roots exist and every Finance write takes one of them first (§15.1): the global
 * policy root `finance_policy_roots` — taken exclusively by a mutating policy command and shared, and
 * first, by every policy consumer and by every command with no Teacher of its own — and the per-Teacher
 * `finance_teacher_roots` row. Nothing here ever takes a root after a Teacher-scoped row, and no Finance
 * class writes anything outside the declared Finance tables plus the two declared infrastructure seams
 * (`platform_audit_events`, `platform_outbox`), both insert-only (§15.7).
 */
final class FinanceSupport {
    /** The reasons whose open exception blocks statement issuance (§10.5 rule 9). */
    private const BLOCKING_REASON_CODES=array(
        'finance_policy_key_not_allowed','finance_policy_version_conflict','finance_policy_effective_from_missing',
        'finance_policy_value_type_invalid','finance_policy_timeline_overlap','policy_effective_from_precedes_recorded_consumption',
        'finance_policy_unset','policy_unset_for_statement_period','rate_missing_for_lesson','ambiguous_teacher_rate',
        'teacher_rate_timeline_overlap','teacher_rate_timeline_gap','teacher_rate_state_not_resolvable','rate_scope_violation',
        'rate_referenced_by_snapshot','rate_effective_from_precedes_snapshot','occurrence_anchor_missing',
        'finance_lesson_kind_not_allowed','finance_amount_not_exact','snapshot_lesson_not_finalised','snapshot_missing_for_lesson',
        'snapshot_correction_incomplete','payability_pending','payability_conflicts_with_delivery_fact',
        'payability_supersession_conflict','statement_period_overlap','statement_totals_mismatch','statement_derivation_mismatch',
        'statement_timezone_representation_invalid','lesson_stated_twice','lesson_not_finalised_in_period',
        'currency_mismatch_for_statement','upstream_aggregate_invalid',
    );

    public static function requireCapability(string $capability):void{
        if(!current_user_can($capability))throw new \RuntimeException('Unauthorized');
    }
    public static function actor(string $message='Finance actor unavailable'):int{
        $id=get_current_user_id();
        if($id<1)throw new \RuntimeException($message);
        return $id;
    }
    public static function now():string{return gmdate('Y-m-d H:i:s');}

    public static function key(string $rawKey):string{return FinanceIdempotency::commandKey($rawKey);}
    public static function payload(array $facts):string{return FinanceIdempotency::payload($facts);}
    public static function digest(array $fields,array $values):string{return FinanceIdempotency::derivation($fields,$values);}

    /** A controlled reason chosen from the operator set; never free text (§5.2.1, §5.4). */
    public static function operatorReason(array $input,string $field='reason_code'):string{
        $reason=(string)($input[$field]??'');
        if(!FinanceRule::operatorReason($reason))throw new \InvalidArgumentException('finance_vocabulary_member_not_allowed');
        return $reason;
    }
    /** §5.4: a caller may never supply a reason literal of its own choosing. */
    public static function requireReason(string $reason):string{
        if(!FinanceRule::reasonCode($reason))throw new \InvalidArgumentException('finance_vocabulary_member_not_allowed');
        return $reason;
    }
    public static function requireExceptionReason(string $reason):string{
        if(!FinanceRule::exceptionReason($reason))throw new \InvalidArgumentException('finance_vocabulary_member_not_allowed');
        return $reason;
    }
    public static function requireFindingCode(string $code):string{
        if(!FinanceRule::findingCode($code))throw new \InvalidArgumentException('finance_vocabulary_member_not_allowed');
        return $code;
    }
    /** §10.5 rule 9: whether an open exception for this reason blocks statement issuance. */
    public static function blocking(string $reasonCode):bool{
        return in_array($reasonCode,self::BLOCKING_REASON_CODES,true);
    }
    public static function severity(string $reasonCode):string{return self::blocking($reasonCode)?'blocking':'informational';}

    /** §5.4/U-D18: a validated, digested evidence triple. */
    public static function evidence(array $input,string $referenceKey='evidence_reference'):array{
        $channel=(string)($input['evidence_channel']??'');
        if(!in_array($channel,FinanceRule::EVIDENCE_CHANNELS,true))throw new \InvalidArgumentException('Controlled evidence channel required');
        $at=(string)($input['evidence_at']??'');
        if(!FinanceRule::utc($at)||$at>self::now())throw new \InvalidArgumentException('Valid past-or-present UTC evidence time required');
        $reference=(string)($input[$referenceKey]??'');
        if(trim($reference)==='')throw new \InvalidArgumentException('Evidence reference required');
        return array('channel'=>$channel,'at'=>$at,'digest'=>FinanceIdempotency::evidence($reference));
    }
    public static function positiveInt(mixed $value,string $message):int{
        if(is_int($value))$int=$value;
        elseif(is_string($value)&&preg_match('/^\d{1,15}$/D',trim($value))===1)$int=(int)trim($value);
        else throw new \InvalidArgumentException($message);
        if($int<1)throw new \InvalidArgumentException($message);
        return $int;
    }
    public static function amount(mixed $value,string $message):int{
        if(is_int($value))$int=$value;
        elseif(is_string($value)&&preg_match('/^\d{1,15}$/D',trim($value))===1)$int=(int)trim($value);
        else throw new \InvalidArgumentException($message);
        if($int<0)throw new \InvalidArgumentException($message);
        return $int;
    }
    /** U-D1/§5.4: an explicit three-letter ISO-4217 currency, never a converted or defaulted one. */
    public static function currency(string $currency):string{
        $value=strtoupper(trim($currency));
        if(preg_match('/^[A-Z]{3}$/D',$value)!==1)throw new \InvalidArgumentException('Explicit ISO-4217 currency required');
        return $value;
    }
    public static function utc(mixed $value,string $message):string{
        $value=is_string($value)?trim($value):'';
        if(!FinanceRule::utc($value))throw new \InvalidArgumentException($message);
        return $value;
    }
    /** Fail-closed aggregate proof: a malformed stored aggregate is never presented as authority. */
    public static function assertIntegrity(bool $valid,string $reason):void{
        if(!$valid)throw new \RuntimeException(FinanceRule::exceptionReason($reason)?$reason:'upstream_aggregate_invalid');
    }

    // ---------------------------------------------------------------------
    // Serialisation roots (§15.1–§15.2)
    // ---------------------------------------------------------------------

    /**
     * The global policy root, taken first and in the declared mode.
     *
     * `$exclusive` is true only for the mutating policy commands (`record`/`supersede`/`withdraw`);
     * every policy consumer and every teacher-less command takes the same root shared. The root row is a
     * structural arbitration row seeded by migration 030 and is never created, moved or deleted here.
     */
    public static function lockPolicyRoot(bool $exclusive):void{
        global $wpdb;$p=$wpdb->prefix.'dzn_';
        $row=$wpdb->get_row("SELECT id FROM {$p}finance_policy_roots WHERE root_key='".FinanceRule::POLICY_ROOT_KEY."'".($exclusive?' FOR UPDATE':' LOCK IN SHARE MODE'));
        if(!$row)throw new \RuntimeException('Finance policy root unavailable');
    }
    /** The per-Teacher finance root, created lazily and taken for the whole transaction. */
    public static function lockTeacherRoot(int $teacherId,int $actor):int{
        global $wpdb;$p=$wpdb->prefix.'dzn_';
        $teacherId=self::positiveInt($teacherId,'Valid Teacher required');
        $rootId=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}finance_teacher_roots WHERE teacher_id=%d",$teacherId));
        if($rootId<1){
            $old=$wpdb->suppress_errors(true);
            $wpdb->insert($p.'finance_teacher_roots',array('teacher_id'=>$teacherId,'created_at'=>self::now(),'created_by'=>$actor));
            $wpdb->suppress_errors($old);
            $rootId=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}finance_teacher_roots WHERE teacher_id=%d",$teacherId));
        }
        if($rootId<1)throw new \RuntimeException('Finance teacher root unavailable');
        $locked=$wpdb->get_row($wpdb->prepare("SELECT id FROM {$p}finance_teacher_roots WHERE id=%d FOR UPDATE",$rootId));
        if(!$locked)throw new \RuntimeException('Finance teacher root unavailable');
        return $rootId;
    }
    /** §15.2: the global policy root is taken shared and *before* any Teacher-scoped row. */
    public static function lockPolicyRootThenTeacher(int $teacherId,int $actor):int{
        self::lockPolicyRoot(false);
        return self::lockTeacherRoot($teacherId,$actor);
    }

    // ---------------------------------------------------------------------
    // Declared infrastructure seams (§15.7)
    // ---------------------------------------------------------------------

    /**
     * One digest-only audit row per audited Finance row, inside the caller's transaction.
     *
     * The row carries identifiers, the derived key, a §5.2.1 reason code (or `NULL` for a plain success)
     * and a short digest/short-code `safe_detail` — never an amount, a raw key, a request body, a
     * provider reference, an evidence payload or personal data.
     */
    public static function audit(string $aggregate,int $aggregateId,string $eventType,int $actor,string $commandKeyDigest,?string $reasonCode,string $occurredAt,?string $safeDetail=null):void{
        global $wpdb;$p=$wpdb->prefix.'dzn_';
        if(!in_array($aggregate,FinanceRule::TABLES,true))throw new \InvalidArgumentException('Declared Finance aggregate required');
        if($reasonCode!==null)self::requireReason($reasonCode);
        $key=FinanceIdempotency::auditKey($commandKeyDigest,$aggregate,$aggregateId);
        if($wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}platform_audit_events WHERE idempotency_key=%s",$key))!==null)return;
        self::hook('dzn_phase_2a2u_evidence','audit',$aggregate,$aggregateId);
        $detail=$safeDetail===null?null:substr($safeDetail,0,190);
        $old=$wpdb->suppress_errors(true);
        $ok=$wpdb->insert($p.'platform_audit_events',array(
            'aggregate_type'=>substr($aggregate,0,32),'aggregate_id'=>$aggregateId,'event_type'=>substr($eventType,0,64),
            'actor_type'=>'user','actor_id'=>$actor,'reason_code'=>$reasonCode,'safe_detail'=>$detail,
            'idempotency_key'=>$key,'occurred_at'=>$occurredAt,
        ));
        $error=$wpdb->last_error;
        $wpdb->suppress_errors($old);
        if($ok===false&&$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}platform_audit_events WHERE idempotency_key=%s",$key))===null)throw new \RuntimeException('Finance audit persistence failed: '.$error);
    }

    /**
     * §17: one identity-only notification intent, inserted against the unchanged `platform_outbox` seam.
     *
     * The row carries the seam's three identity columns plus only the seam's own mandatory insert
     * metadata (the derived `idempotency_key`, the initial `pending` state, `available_at`/`created_at`
     * at the transition instant, `attempt_count = 0` and the explicit `NULL` lease/legacy-identity
     * columns) and no Finance business fact of any kind.
     */
    public static function outboxIntent(string $aggregate,int $aggregateId,string $intent,string $occurredAt):void{
        global $wpdb;$p=$wpdb->prefix.'dzn_';
        if(!FinanceRule::notificationIntent($intent))throw new \InvalidArgumentException('Controlled notification intent required');
        if((FinanceRule::INTENT_AGGREGATES[$intent]??null)!==$aggregate)throw new \InvalidArgumentException('Controlled notification aggregate required');
        $key=FinanceIdempotency::intentKey($aggregate,$aggregateId,$intent);
        if($wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}platform_outbox WHERE idempotency_key=%s",$key))!==null)return;
        self::hook('dzn_phase_2a2u_evidence','outbox',$aggregate,$aggregateId);
        $old=$wpdb->suppress_errors(true);
        $ok=$wpdb->insert($p.'platform_outbox',array(
            'aggregate_type'=>substr($aggregate,0,32),'aggregate_id'=>$aggregateId,'event_type'=>substr($intent,0,64),
            'invitation_id'=>null,'generation_id'=>null,
            'idempotency_key'=>$key,'status'=>'pending','available_at'=>$occurredAt,'leased_at'=>null,
            'processed_at'=>null,'attempt_count'=>0,'created_at'=>$occurredAt,
        ));
        $error=$wpdb->last_error;
        $wpdb->suppress_errors($old);
        // A concurrent identical intent is convergence, not corruption: the winner's row stands.
        if($ok===false&&$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}platform_outbox WHERE idempotency_key=%s",$key))===null)throw new \RuntimeException('Finance notification intent persistence failed: '.$error);
    }

    // ---------------------------------------------------------------------
    // Durable exceptions (U-D16, §13.2)
    // ---------------------------------------------------------------------

    /**
     * Ensure one open `finance_exceptions` row exists for a condition, converging an identical repeat
     * on the same fingerprint rather than duplicating evidence.
     */
    public static function exception(string $reasonCode,string $summary,?array $scope,int $actor,string $occurredAt,?string $safeDetail=null):int{
        global $wpdb;$p=$wpdb->prefix.'dzn_';
        $reasonCode=self::requireExceptionReason($reasonCode);
        $scope=$scope??array();
        $fingerprint=FinanceIdempotency::fingerprint($reasonCode,array(
            'teacher_id'=>$scope['teacher_id']??null,'lesson_id'=>$scope['lesson_id']??null,
            'statement_id'=>$scope['statement_id']??null,'snapshot_id'=>$scope['snapshot_id']??null,
        ));
        $existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}finance_exceptions WHERE fingerprint=%s AND state='open' FOR UPDATE",$fingerprint));
        if($existing){
            $wpdb->query($wpdb->prepare("UPDATE {$p}finance_exceptions SET last_seen_at=%s,occurrence_count=occurrence_count+1,updated_at=%s,updated_by=%d WHERE id=%d",$occurredAt,$occurredAt,$actor,(int)$existing->id));
            return (int)$existing->id;
        }
        $old=$wpdb->suppress_errors(true);
        $ok=$wpdb->insert($p.'finance_exceptions',array(
            'uid'=>\Delnavazan\Platform\Core\Support\Identifier::uid(),'reference_code'=>null,
            'reason_code'=>$reasonCode,'severity'=>self::severity($reasonCode),'state'=>'open','fingerprint'=>$fingerprint,
            'summary'=>substr($summary,0,255),'safe_detail'=>$safeDetail===null?null:substr($safeDetail,0,190),
            'teacher_id'=>$scope['teacher_id']??null,'lesson_id'=>$scope['lesson_id']??null,
            'statement_id'=>$scope['statement_id']??null,'snapshot_id'=>$scope['snapshot_id']??null,
            'detected_at'=>$occurredAt,'last_seen_at'=>$occurredAt,'occurrence_count'=>1,
            'resolved_at'=>null,'resolved_by'=>null,'resolution_note'=>null,
            'created_at'=>$occurredAt,'created_by'=>$actor,'updated_at'=>$occurredAt,'updated_by'=>$actor,
        ));
        $error=$wpdb->last_error;
        $wpdb->suppress_errors($old);
        if($ok===false)throw new \RuntimeException('Finance exception persistence failed: '.$error);
        $id=(int)$wpdb->insert_id;
        $wpdb->update($p.'finance_exceptions',array('reference_code'=>\Delnavazan\Platform\Core\Support\Identifier::reference('FINEX', $id)),array('id'=>$id));
        return $id;
    }

    /**
     * §15.8: commit exactly a business refusal's evidence.
     *
     * The attempted mutation has already rolled back, so this second, short transaction re-acquires the
     * same root the command's own scope selected and commits only the refused command row, the matching
     * exception row and their digest-only audit rows. A replayed refusal converges on the original
     * evidence through the command table's `UNIQUE command_key_digest`.
     */
    public static function commitRefusal(callable $lock,string $commandTable,array $commandRow,string $reasonCode,string $summary,?array $scope,int $actor,?string $safeDetail=null):int{
        global $wpdb;$p=$wpdb->prefix.'dzn_';
        if(!in_array($commandTable,FinanceRule::TABLES,true))throw new \InvalidArgumentException('Declared Finance command table required');
        $reasonCode=self::requireExceptionReason($reasonCode);
        $occurredAt=(string)$commandRow['created_at'];
        self::begin();
        try{
            $lock();
            $existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}{$commandTable} WHERE command_key_digest=%s",(string)$commandRow['command_key_digest']));
            if($existing){
                $commandId=(int)$existing->id;
                if((string)$existing->reason_code===$reasonCode)$reasonCode=(string)$existing->reason_code;
            }else{
                $commandRow['reason_code']=$reasonCode;
                self::insertRow($commandTable,$commandRow,'Finance refusal command persistence failed');
                $commandId=(int)$wpdb->insert_id;
            }
            $exceptionId=self::exception($reasonCode,$summary,$scope,$actor,$occurredAt,$safeDetail);
            $commandRow['id']=$commandId;
            self::audit($commandTable,$commandId,(string)$commandRow['operation'],$actor,(string)$commandRow['command_key_digest'],$reasonCode,$occurredAt,null);
            self::audit('finance_exceptions',$exceptionId,$reasonCode,$actor,(string)$commandRow['command_key_digest'],$reasonCode,$occurredAt,null);
            self::commit();
            return $commandId;
        }catch(\Throwable$e){
            self::rollback();
            throw $e;
        }
    }

    /** A bounded, amount-free human summary for a failure/diagnostic surface. */
    public static function summary(string $code,string $subject):string{
        return ucfirst(str_replace('_',' ',$code)).' ('.$subject.')';
    }

    /** Deterministic test seam: mirrors the established phase hook convention without exposing authority. */
    public static function hook(string $moment,...$arguments):void{
        if(function_exists('do_action'))do_action($moment,...$arguments);
    }

    /**
     * §15.6–§15.8: the declared command lifecycle.
     *
     * The attempt runs inside its own transaction under the root `$lock` acquires — always the one the
     * command's target scope selected, and always first. A {@see FinanceRefusalException} rolls the
     * attempted mutation back and then commits exactly the refusal evidence in the §15.8
     * refusal-evidence transaction; every other failure rolls the whole command back and is never
     * converted into a refusal.
     *
     * @param callable():void          $lock
     * @param array<string,mixed>      $commandRow the refused row template (selectors, digests, operation)
     * @param callable():array         $attempt
     * @return array
     */
    public static function runCommand(callable $lock,string $commandTable,array $commandRow,callable $attempt):array{
        try{
            self::begin();
            try{
                $lock();
                $result=$attempt();
                self::commit();
                return $result;
            }catch(FinanceRefusalException $e){
                self::rollback();
                throw $e;
            }catch(\Throwable$e){
                self::rollback();
                // §15.8: a corrupt shape a section names as a refusal outcome — anywhere in the attempt,
                // including a fail-closed integrity validator — is recorded as a business refusal; a shape
                // no section declares an outcome for stays a persistence/corruption failure.
                throw self::refusalFrom($e);
            }
        }catch(FinanceRefusalException $e){
            self::commitRefusal($lock,$commandTable,$commandRow,$e->reasonCode(),$e->summary(),self::scopeOf($e,$commandRow),(int)($commandRow['created_by']??0));
            throw $e;
        }
    }

    /** Convert a fail-closed validator's declared reason code into the refusal the command records. */
    public static function refusalFrom(\Throwable $e):\Throwable{
        if($e instanceof FinanceRefusalException)return $e;
        $code=(string)$e->getMessage();
        if(FinanceRule::exceptionReason($code))return new FinanceRefusalException($code,self::summary($code,'finance refusal'),array(),$e);
        return $e;
    }
    /** The declared exception scope implied by the command's own target row. */
    private static function scopeOf(FinanceRefusalException $e,array $commandRow):array{
        $scope=$e->scope();
        if($scope!==array())return $scope;
        foreach(array('teacher_id','lesson_id','statement_id','snapshot_id') as $key)if(array_key_exists($key,$commandRow)&&$commandRow[$key]!==null)$scope[$key]=(int)$commandRow[$key];
        return $scope;
    }

    // ---------------------------------------------------------------------
    // Transactions and row helpers
    // ---------------------------------------------------------------------

    public static function begin():void{
        global $wpdb;
        if(false===$wpdb->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED'))throw new \RuntimeException('Transaction start failed');
        if(false===$wpdb->query('START TRANSACTION'))throw new \RuntimeException('Transaction start failed');
    }
    public static function commit():void{global $wpdb;if(false===$wpdb->query('COMMIT'))throw new \RuntimeException('Transaction commit failed');}
    public static function rollback():void{global $wpdb;$wpdb->query('ROLLBACK');}
    /** An insert that fails closed with its own message, suppressing the WordPress print path. */
    public static function insertRow(string $table,array $data,string $message):int{
        global $wpdb;$p=$wpdb->prefix.'dzn_';
        if(!in_array($table,FinanceRule::TABLES,true))throw new \InvalidArgumentException('Declared Finance table required');
        // Deterministic failure seam: a test (or an operator diagnostic) may observe or refuse a write by
        // table, which is how §18's injected-failure suite reaches every mutation boundary.
        self::hook('dzn_phase_2a2u_write',$table,$data);
        $old=$wpdb->suppress_errors(true);
        $ok=$wpdb->insert($p.$table,$data);
        $error=$wpdb->last_error;
        $wpdb->suppress_errors($old);
        if($ok===false)throw new \RuntimeException($message.': '.$error);
        return (int)$wpdb->insert_id;
    }
    /** A single compare-and-swap update whose affected-row count is the outcome. */
    public static function compareAndSwap(string $table,array $values,array $where,string $message):int{
        global $wpdb;$p=$wpdb->prefix.'dzn_';
        if(!in_array($table,FinanceRule::TABLES,true))throw new \InvalidArgumentException('Declared Finance table required');
        self::hook('dzn_phase_2a2u_write',$table,$values);
        $changed=$wpdb->update($p.$table,$values,$where);
        if($changed===false)throw new \RuntimeException($message.': '.$wpdb->last_error);
        return (int)$changed;
    }
}
