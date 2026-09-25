<?php
namespace Delnavazan\Platform\Core\Infrastructure\Repository;

/**
 * Persistence boundary for the Phase 2A.2-T provider-account, mapping and provider-event storage
 * (contract §7, §9, §12).
 *
 * Every account row, mapping row and provider event is stored digest-only: no raw provider reference,
 * no raw payload, no raw signature and no raw source address is ever written. Duplicate arbitration is
 * always by an explicit named index, never by a read-then-write race.
 */
final class PaymentProviderRepository {
    private string $p;
    public function __construct(){global $wpdb;$this->p=$wpdb->prefix.'dzn_';}

    public function begin():void{global $wpdb;if($wpdb->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED')===false||$wpdb->query('START TRANSACTION')===false)throw new \RuntimeException('Transaction start failed');}
    public function commit():void{global $wpdb;if($wpdb->query('COMMIT')===false)throw new \RuntimeException('Transaction commit failed');}
    public function rollback():void{global $wpdb;$wpdb->query('ROLLBACK');}
    private function one(string $sql,...$args):?object{global $wpdb;return $wpdb->get_row($wpdb->prepare($sql,...$args));}

    // --- provider accounts -------------------------------------------------------------------------
    public function account(int $id,bool $lock=false):?object{return $this->one("SELECT * FROM {$this->p}payment_provider_accounts WHERE id=%d".($lock?' FOR UPDATE':''),$id);}
    public function accountByReferenceCode(string $referenceCode,bool $lock=false):?object{
        $referenceCode=trim($referenceCode);
        if($referenceCode==='')return null;
        return $this->one("SELECT * FROM {$this->p}payment_provider_accounts WHERE reference_code=%s".($lock?' FOR UPDATE':''),$referenceCode);
    }
    public function accountsForProvider(string $providerKey):array{
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->p}payment_provider_accounts WHERE provider_key=%s ORDER BY id ASC",$providerKey))?:array();
    }
    public function allAccounts():array{
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$this->p}payment_provider_accounts ORDER BY id ASC")?:array();
    }
    public function accountsByReferenceCode(string $referenceCode,bool $lock=false):array{
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->p}payment_provider_accounts WHERE reference_code=%s".($lock?' FOR UPDATE':''),trim($referenceCode)))?:array();
    }
    public function insertAccount(array $data):int{return $this->insert('payment_provider_accounts',$data);}
    public function updateAccount(int $id,int $expectedVersion,array $fields,string $now,int $actor):int{
        $changes=array_merge($fields,array('account_version'=>$expectedVersion+1,'updated_at'=>$now,'updated_by'=>$actor));
        return $this->update('payment_provider_accounts',$changes,array('id'=>$id,'account_version'=>$expectedVersion));
    }
    public function maxAccountEventSequence(int $accountId):int{
        global $wpdb;
        return (int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(MAX(event_sequence),0)+1 FROM {$this->p}payment_provider_account_events WHERE payment_provider_account_id=%d",$accountId));
    }
    public function insertAccountEvent(array $data):int{return $this->insert('payment_provider_account_events',$data);}
    public function accountEvents(int $accountId):array{
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->p}payment_provider_account_events WHERE payment_provider_account_id=%d ORDER BY event_sequence ASC",$accountId))?:array();
    }
    public function insertAccountCommand(array $data):int{return $this->insert('payment_provider_account_commands',$data);}

    // --- provider object mappings -------------------------------------------------------------------
    public function object(int $id,bool $lock=false):?object{return $this->one("SELECT * FROM {$this->p}payment_provider_objects WHERE id=%d".($lock?' FOR UPDATE':''),$id);}
    public function activeObject(int $accountId,string $canonicalKind,int $canonicalId,string $objectKind,bool $lock=false):?object{
        return $this->one("SELECT * FROM {$this->p}payment_provider_objects WHERE payment_provider_account_id=%d AND canonical_kind=%s AND canonical_id=%d AND object_kind=%s AND active_slot=1".($lock?' FOR UPDATE':''),$accountId,$canonicalKind,$canonicalId,$objectKind);
    }
    public function objectByReferenceDigest(int $accountId,string $objectKind,string $referenceDigest,bool $lock=false):?object{
        return $this->one("SELECT * FROM {$this->p}payment_provider_objects WHERE payment_provider_account_id=%d AND object_kind=%s AND object_reference_digest=%s".($lock?' FOR UPDATE':''),$accountId,$objectKind,$referenceDigest);
    }
    /**
     * [C8-2] Every *active* mapping of one provider object reference, whatever its kind.
     *
     * The provider-side unique index is kind-scoped, so one raw reference could in principle be linked
     * under two different kinds. The caller requires exactly one candidate and refuses an ambiguous
     * object rather than choosing one, and a historical row (`active_slot IS NULL` — superseded or
     * detached) is never returned, because it is no longer authority for attribution.
     */
    public function activeObjectsByReferenceDigest(int $accountId,string $referenceDigest,bool $lock=false):array{
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->p}payment_provider_objects WHERE payment_provider_account_id=%d AND object_reference_digest=%s AND active_slot=1".($lock?' FOR UPDATE':''),$accountId,$referenceDigest))?:array();
    }
    /** [C8-2] The R1 obligation one canonical collection intent already owns (NULL when it owns none). */
    public function obligationForCollectionIntent(int $collectionIntentId):?int{
        $row=$this->one("SELECT obligation_id FROM {$this->p}collection_intents WHERE id=%d",$collectionIntentId);
        return $row?(int)$row->obligation_id:null;
    }
    public function objectsForCanonical(string $canonicalKind,int $canonicalId):array{
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->p}payment_provider_objects WHERE canonical_kind=%s AND canonical_id=%d ORDER BY id ASC",$canonicalKind,$canonicalId))?:array();
    }
    public function allObjects():array{
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$this->p}payment_provider_objects ORDER BY id ASC")?:array();
    }
    public function insertObject(array $data):int{return $this->insert('payment_provider_objects',$data);}
    public function updateObject(int $id,int $expectedVersion,array $fields,string $now,int $actor):int{
        $changes=array_merge($fields,array('link_version'=>$expectedVersion+1,'updated_at'=>$now,'updated_by'=>$actor));
        return $this->update('payment_provider_objects',$changes,array('id'=>$id,'link_version'=>$expectedVersion));
    }
    public function maxObjectEventSequence(int $objectId):int{
        global $wpdb;
        return (int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(MAX(event_sequence),0)+1 FROM {$this->p}payment_provider_object_events WHERE payment_provider_object_id=%d",$objectId));
    }
    public function insertObjectEvent(array $data):int{return $this->insert('payment_provider_object_events',$data);}
    public function objectEvents(int $objectId):array{
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->p}payment_provider_object_events WHERE payment_provider_object_id=%d ORDER BY event_sequence ASC",$objectId))?:array();
    }
    public function insertObjectCommand(array $data):int{return $this->insert('payment_provider_object_commands',$data);}
    public function accountCommand(string $digest):?object{return $this->one("SELECT * FROM {$this->p}payment_provider_account_commands WHERE command_key_digest=%s",$digest);}
    public function objectCommand(string $digest):?object{return $this->one("SELECT * FROM {$this->p}payment_provider_object_commands WHERE command_key_digest=%s",$digest);}

    // --- provider event receipts, events and decisions ----------------------------------------------
    public function insertReceipt(array $data):int{return $this->insert('payment_provider_event_receipts',$data);}
    public function receipt(int $id):?object{return $this->one("SELECT * FROM {$this->p}payment_provider_event_receipts WHERE id=%d",$id);}
    public function receipts():array{
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$this->p}payment_provider_event_receipts ORDER BY id ASC")?:array();
    }
    public function insertEvent(array $data):int{return $this->insert('payment_provider_events',$data);}
    public function event(int $id,bool $lock=false):?object{return $this->one("SELECT * FROM {$this->p}payment_provider_events WHERE id=%d".($lock?' FOR UPDATE':''),$id);}
    public function eventByReferenceDigest(string $providerKey,string $eventReferenceDigest,bool $lock=false):?object{
        return $this->one("SELECT * FROM {$this->p}payment_provider_events WHERE provider_key=%s AND event_reference_digest=%s".($lock?' FOR UPDATE':''),$providerKey,$eventReferenceDigest);
    }
    public function events():array{
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$this->p}payment_provider_events ORDER BY id ASC")?:array();
    }
    public function maxDecisionSequence(int $eventId):int{
        global $wpdb;
        return (int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(MAX(decision_sequence),0)+1 FROM {$this->p}payment_provider_event_decisions WHERE provider_event_id=%d",$eventId));
    }
    public function insertDecision(array $data):int{return $this->insert('payment_provider_event_decisions',$data);}
    public function decisions(int $eventId):array{
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->p}payment_provider_event_decisions WHERE provider_event_id=%d ORDER BY decision_sequence ASC",$eventId))?:array();
    }
    public function latestDecision(int $eventId):?object{
        return $this->one("SELECT * FROM {$this->p}payment_provider_event_decisions WHERE provider_event_id=%d ORDER BY decision_sequence DESC LIMIT 1",$eventId);
    }
    public function allDecisions():array{
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$this->p}payment_provider_event_decisions ORDER BY id ASC")?:array();
    }

    public function duplicate(\Throwable $e):?string{
        if(!preg_match("/Duplicate entry .* for key ['`](?:[^'`.]+\\.)?([^'`]+)['`]/i",$e->getMessage(),$m))return null;
        $key=strtolower((string)$m[1]);
        return in_array($key,array(
            'uid','reference_code','provider_account','provider_object','canonical_object','secret_slot',
            'account_sequence','object_sequence','provider_event','decision_sequence','request_digest',
            'command_key_digest','command_result','command_dispatch','subject_claim',
        ),true)?$key:null;
    }

    private function insert(string $table,array $data):int{
        global $wpdb;
        $old=$wpdb->suppress_errors(true);
        $ok=$wpdb->insert($this->p.$table,$data);
        $error=$wpdb->last_error;
        $wpdb->suppress_errors($old);
        if($ok===false)throw new PersistenceException((int)$wpdb->last_errno,(string)$error,'insert '.$table);
        return (int)$wpdb->insert_id;
    }
    private function update(string $table,array $changes,array $where):int{
        global $wpdb;
        $old=$wpdb->suppress_errors(true);
        $result=$wpdb->update($this->p.$table,$changes,$where);
        $error=$wpdb->last_error;
        $wpdb->suppress_errors($old);
        if($result===false)throw new \RuntimeException('Payment provider persistence failed: '.$error);
        return (int)$result;
    }
}
