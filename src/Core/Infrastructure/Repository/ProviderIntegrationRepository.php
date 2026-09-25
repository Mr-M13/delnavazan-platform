<?php
namespace Delnavazan\Platform\Core\Infrastructure\Repository;

/**
 * Persistence for the Phase 2A.2-V provider-neutral integration storage.
 *
 * Every provider reference value that reaches this class is already a keyed digest; the repository
 * never receives a raw provider key, a raw payload, an access token or a refresh token. Credential
 * rows carry only ciphertext/nonce plus their key and cipher versions, appended by the secret
 * service. Commands persist digests, never the command key or the command payload.
 *
 * Lock order (Phase-V §12) is implemented by {@see lockLessonRoots()}: Student–Course identity root →
 * Enrolment → Term → canonical Lesson, and only after that may a caller take a provider connection,
 * mapping, ingest or command row. Nothing in this class locks an integration row before the canonical
 * Lesson chain.
 */
final class ProviderIntegrationRepository {
    private string $p;

    public function __construct(){global $wpdb;$this->p=$wpdb->prefix.'dzn_';}

    public function begin():void{global $wpdb;if($wpdb->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED')===false||$wpdb->query('START TRANSACTION')===false)throw new \RuntimeException('Transaction start failed');}
    public function commit():void{global $wpdb;if($wpdb->query('COMMIT')===false)throw new \RuntimeException('Transaction commit failed');}
    public function rollback():void{global $wpdb;$wpdb->query('ROLLBACK');}

    /** Take the canonical Lesson chain in the Phase-V lock order before touching integration rows. */
    public function lockLessonRoots(int $lessonId):array{
        global $wpdb;
        $lesson=$this->row("SELECT * FROM {$this->p}lessons WHERE id=%d FOR UPDATE",$lessonId);
        if(!$lesson)throw new \InvalidArgumentException('canonical_lesson_required');
        $enrolment=$lesson->enrolment_id===null?null:$this->row("SELECT * FROM {$this->p}enrolments WHERE id=%d FOR UPDATE",(int)$lesson->enrolment_id);
        $term=$lesson->term_id===null?null:$this->row("SELECT * FROM {$this->p}terms WHERE id=%d FOR UPDATE",(int)$lesson->term_id);
        $root=null;
        if($enrolment){
            $root=$this->row("SELECT * FROM {$this->p}enrolment_identity_roots WHERE student_id=%d AND course_id=%d FOR UPDATE",(int)$enrolment->student_id,(int)$enrolment->course_id);
            if(!$root)throw new \RuntimeException('Enrolment identity root unavailable');
        }
        return array('root'=>$root,'enrolment'=>$enrolment,'term'=>$term,'lesson'=>$lesson);
    }

    // ---- Connections -------------------------------------------------------------------------
    /** The exact Core Teacher an authenticated principal resolves to through the Phase-J link. */
    public function teacherForPrincipalUser(int $userId,bool $lock=false):?object{
        return $this->row("SELECT t.* FROM {$this->p}teacher_principal_links l INNER JOIN {$this->p}teachers t ON t.id=l.teacher_id WHERE l.wordpress_user_id=%d AND l.status='active' AND l.revoked_at IS NULL AND t.status='active' AND t.archived_at IS NULL".($lock?' FOR UPDATE':''),$userId);
    }
    public function teacher(int $id,bool $lock=false):?object{
        return $this->row("SELECT * FROM {$this->p}teachers WHERE id=%d".($lock?' FOR UPDATE':''),$id);
    }
    public function activeConnection(string $providerCode,int $teacherId,bool $lock=false):?object{
        return $this->row("SELECT * FROM {$this->p}integration_connections WHERE provider_code=%s AND teacher_id=%d AND active_slot=1".($lock?' FOR UPDATE':''),$providerCode,$teacherId);
    }
    public function connection(int $id,bool $lock=false):?object{
        return $this->row("SELECT * FROM {$this->p}integration_connections WHERE id=%d".($lock?' FOR UPDATE':''),$id);
    }
    /** Every consent attempt of one Teacher+provider, so competing lifecycles can be settled. */
    public function authorizingConnections(string $providerCode,int $teacherId,bool $lock=false):array{
        return $this->rows("SELECT * FROM {$this->p}integration_connections WHERE provider_code=%s AND teacher_id=%d AND connection_state='authorizing' ORDER BY lifecycle_sequence,id".($lock?' FOR UPDATE':''),$providerCode,$teacherId);
    }
    public function connections(string $providerCode,int $teacherId):array{
        return $this->rows("SELECT * FROM {$this->p}integration_connections WHERE provider_code=%s AND teacher_id=%d ORDER BY lifecycle_sequence,id",$providerCode,$teacherId);
    }
    public function maxLifecycleSequence(string $providerCode,int $teacherId):int{
        global $wpdb;return(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(MAX(lifecycle_sequence),0) FROM {$this->p}integration_connections WHERE provider_code=%s AND teacher_id=%d",$providerCode,$teacherId));
    }
    public function insertConnection(array $data):int{return $this->insert('integration_connections',$data);}
    public function updateConnection(int $id,array $data,array $where):int{return $this->update('integration_connections',$data,array_merge(array('id'=>$id),$where));}

    // ---- Credentials -------------------------------------------------------------------------
    public function credential(int $connectionId,bool $lock=false):?object{
        return $this->row("SELECT * FROM {$this->p}integration_credentials WHERE connection_id=%d AND state='active' ORDER BY credential_sequence DESC,id DESC LIMIT 1".($lock?' FOR UPDATE':''),$connectionId);
    }
    public function credentials(int $connectionId):array{
        return $this->rows("SELECT * FROM {$this->p}integration_credentials WHERE connection_id=%d ORDER BY credential_sequence,id",$connectionId);
    }
    public function maxCredentialSequence(int $connectionId):int{
        global $wpdb;return(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(MAX(credential_sequence),0) FROM {$this->p}integration_credentials WHERE connection_id=%d",$connectionId));
    }
    public function insertCredential(array $data):int{
        $data['credential_sequence']=$this->maxCredentialSequence((int)$data['connection_id'])+1;
        return $this->insert('integration_credentials',$data);
    }
    public function updateCredential(int $id,array $data,array $where):int{return $this->update('integration_credentials',$data,array_merge(array('id'=>$id),$where));}

    // ---- Authorization intents ---------------------------------------------------------------
    public function insertAuthorization(array $data):int{return $this->insert('integration_oauth_authorizations',$data);}
    public function authorizationByState(string $stateDigest,bool $lock=false):?object{
        return $this->row("SELECT * FROM {$this->p}integration_oauth_authorizations WHERE state_digest=%s".($lock?' FOR UPDATE':''),$stateDigest);
    }
    public function authorization(int $id,bool $lock=false):?object{
        return $this->row("SELECT * FROM {$this->p}integration_oauth_authorizations WHERE id=%d".($lock?' FOR UPDATE':''),$id);
    }
    public function authorizations(string $providerCode,int $teacherId):array{
        return $this->rows("SELECT * FROM {$this->p}integration_oauth_authorizations WHERE provider_code=%s AND teacher_id=%d ORDER BY id",$providerCode,$teacherId);
    }
    /** The consent intent one exact lifecycle generation was created for. */
    public function authorizationForConnection(int $connectionId,bool $lock=false):?object{
        return $this->row("SELECT * FROM {$this->p}integration_oauth_authorizations WHERE connection_id=%d ORDER BY id DESC LIMIT 1".($lock?' FOR UPDATE':''),$connectionId);
    }
    public function issuedAuthorizations(string $providerCode,int $teacherId,bool $lock=false):array{
        return $this->rows("SELECT * FROM {$this->p}integration_oauth_authorizations WHERE provider_code=%s AND teacher_id=%d AND authorization_state='issued' ORDER BY id".($lock?' FOR UPDATE':''),$providerCode,$teacherId);
    }
    public function updateAuthorization(int $id,array $data,array $where):int{return $this->update('integration_oauth_authorizations',$data,array_merge(array('id'=>$id),$where));}

    // ---- Identity mappings -------------------------------------------------------------------
    public function activeIdentityMapping(string $providerCode,string $subjectDigest,bool $lock=false):?object{
        return $this->row("SELECT * FROM {$this->p}provider_identity_mappings WHERE provider_code=%s AND subject_digest=%s AND active_slot=1".($lock?' FOR UPDATE':''),$providerCode,$subjectDigest);
    }
    public function identityMapping(int $id,bool $lock=false):?object{
        return $this->row("SELECT * FROM {$this->p}provider_identity_mappings WHERE id=%d".($lock?' FOR UPDATE':''),$id);
    }
    public function identityMappings(string $providerCode,int $teacherId):array{
        return $this->rows("SELECT * FROM {$this->p}provider_identity_mappings WHERE provider_code=%s AND teacher_id=%d ORDER BY id",$providerCode,$teacherId);
    }
    public function insertIdentityMapping(array $data):int{return $this->insert('provider_identity_mappings',$data);}
    public function updateIdentityMapping(int $id,array $data,array $where):int{return $this->update('provider_identity_mappings',$data,array_merge(array('id'=>$id),$where));}

    // ---- Calendar event mappings -------------------------------------------------------------
    public function activeCalendarMapping(int $lessonId,int $scheduleVersionId,bool $lock=false):?object{
        return $this->row("SELECT * FROM {$this->p}provider_calendar_event_mappings WHERE lesson_id=%d AND schedule_version_id=%d AND active_slot=1".($lock?' FOR UPDATE':''),$lessonId,$scheduleVersionId);
    }
    public function pendingCalendarMapping(int $lessonId,int $scheduleVersionId,bool $lock=false):?object{
        return $this->row("SELECT * FROM {$this->p}provider_calendar_event_mappings WHERE lesson_id=%d AND schedule_version_id=%d AND projection_state='pending'".($lock?' FOR UPDATE':''),$lessonId,$scheduleVersionId);
    }
    public function calendarMapping(int $id,bool $lock=false):?object{
        return $this->row("SELECT * FROM {$this->p}provider_calendar_event_mappings WHERE id=%d".($lock?' FOR UPDATE':''),$id);
    }
    public function calendarMappingByEvent(string $providerCode,string $eventDigest,bool $lock=false):?object{
        return $this->row("SELECT * FROM {$this->p}provider_calendar_event_mappings WHERE provider_code=%s AND event_digest=%s AND active_slot=1".($lock?' FOR UPDATE':''),$providerCode,$eventDigest);
    }
    public function calendarMappings(int $lessonId):array{
        return $this->rows("SELECT * FROM {$this->p}provider_calendar_event_mappings WHERE lesson_id=%d ORDER BY id",$lessonId);
    }
    public function insertCalendarMapping(array $data):int{return $this->insert('provider_calendar_event_mappings',$data);}
    public function updateCalendarMapping(int $id,array $data,array $where):int{return $this->update('provider_calendar_event_mappings',$data,array_merge(array('id'=>$id),$where));}

    // ---- Meeting mappings --------------------------------------------------------------------
    public function activeMeetingMapping(int $lessonId,int $scheduleVersionId,bool $lock=false):?object{
        return $this->row("SELECT * FROM {$this->p}provider_meeting_mappings WHERE lesson_id=%d AND schedule_version_id=%d AND active_slot=1".($lock?' FOR UPDATE':''),$lessonId,$scheduleVersionId);
    }
    public function pendingMeetingMapping(int $lessonId,int $scheduleVersionId,bool $lock=false):?object{
        return $this->row("SELECT * FROM {$this->p}provider_meeting_mappings WHERE lesson_id=%d AND schedule_version_id=%d AND projection_state='pending'".($lock?' FOR UPDATE':''),$lessonId,$scheduleVersionId);
    }
    public function meetingMapping(int $id,bool $lock=false):?object{
        return $this->row("SELECT * FROM {$this->p}provider_meeting_mappings WHERE id=%d".($lock?' FOR UPDATE':''),$id);
    }
    public function meetingMappings(int $lessonId):array{
        return $this->rows("SELECT * FROM {$this->p}provider_meeting_mappings WHERE lesson_id=%d ORDER BY id",$lessonId);
    }
    public function insertMeetingMapping(array $data):int{return $this->insert('provider_meeting_mappings',$data);}
    public function updateMeetingMapping(int $id,array $data,array $where):int{return $this->update('provider_meeting_mappings',$data,array_merge(array('id'=>$id),$where));}

    // ---- Provider ingest events --------------------------------------------------------------
    public function ingestEvent(string $providerCode,string $eventKeyDigest,bool $lock=false):?object{
        return $this->row("SELECT * FROM {$this->p}provider_ingest_events WHERE provider_code=%s AND provider_event_key_digest=%s".($lock?' FOR UPDATE':''),$providerCode,$eventKeyDigest);
    }
    public function insertIngestEvent(array $data):int{return $this->insert('provider_ingest_events',$data);}
    public function ingestEvents(int $lessonId):array{
        return $this->rows("SELECT * FROM {$this->p}provider_ingest_events WHERE lesson_id=%d ORDER BY id",$lessonId);
    }
    public function maxEventSequence(string $providerCode):int{
        global $wpdb;return(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(MAX(event_sequence),0) FROM {$this->p}provider_ingest_events WHERE provider_code=%s",$providerCode));
    }

    // ---- Provider event conflicts ------------------------------------------------------------
    public function insertConflict(array $data):int{return $this->insert('provider_event_conflicts',$data);}
    public function conflict(string $providerCode,string $eventKeyDigest,string $conflictingDigest):?object{
        return $this->row("SELECT * FROM {$this->p}provider_event_conflicts WHERE provider_code=%s AND provider_event_key_digest=%s AND conflicting_fact_digest=%s",$providerCode,$eventKeyDigest,$conflictingDigest);
    }
    public function conflicts(int $lessonId):array{
        return $this->rows("SELECT * FROM {$this->p}provider_event_conflicts WHERE lesson_id=%d ORDER BY id",$lessonId);
    }

    // ---- Provider event handoff outcomes -----------------------------------------------------
    /** Append one handoff outcome; the receipt itself is never mutated by this. */
    public function insertIngestOutcome(array $data):int{
        $data['handoff_attempt']=$this->maxHandoffAttempt((int)$data['provider_ingest_event_id'])+1;
        return $this->insert('provider_ingest_outcomes',$data);
    }
    public function maxHandoffAttempt(int $eventId):int{
        global $wpdb;return(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(MAX(handoff_attempt),0) FROM {$this->p}provider_ingest_outcomes WHERE provider_ingest_event_id=%d",$eventId));
    }
    public function latestIngestOutcome(int $eventId,bool $lock=false):?object{
        return $this->row("SELECT * FROM {$this->p}provider_ingest_outcomes WHERE provider_ingest_event_id=%d ORDER BY handoff_attempt DESC, id DESC LIMIT 1".($lock?' FOR UPDATE':''),$eventId);
    }
    public function ingestOutcomes(int $eventId):array{
        return $this->rows("SELECT * FROM {$this->p}provider_ingest_outcomes WHERE provider_ingest_event_id=%d ORDER BY handoff_attempt,id",$eventId);
    }

    // ---- Command evidence --------------------------------------------------------------------
    public function command(string $digest):?object{
        return $this->row("SELECT * FROM {$this->p}provider_integration_commands WHERE command_key_digest=%s",$digest);
    }
    public function insertCommand(array $data):int{return $this->insert('provider_integration_commands',$data);}

    /** Controlled duplicate classification: the caller must never parse a driver message itself. */
    public function duplicate(\Throwable $e):?string{
        if(!preg_match("/Duplicate entry .* for key ['`](?:[^'`.]+\\.)?([^'`]+)['`]/i",$e->getMessage(),$m))return null;
        $key=strtolower($m[1]);
        return in_array($key,array(
            'command_key_digest','active_connection','teacher_sequence','provider_subject','lesson_version','provider_event',
            'provider_conference','conflict_identity','event_sequence','connection_sequence','uid',
        ),true)?$key:null;
    }

    // ---- Primitives --------------------------------------------------------------------------
    private function insert(string $table,array $data):int{
        global $wpdb;
        if($wpdb->insert($this->p.$table,$data)===false)throw new PersistenceException((int)$wpdb->last_errno,(string)$wpdb->last_error,'insert');
        return(int)$wpdb->insert_id;
    }
    private function update(string $table,array $data,array $where):int{
        global $wpdb;
        $result=$wpdb->update($this->p.$table,$data,$where);
        if(false===$result)throw new PersistenceException((int)$wpdb->last_errno,(string)$wpdb->last_error,'update');
        return(int)$result;
    }
    private function row(string $sql,mixed ...$args):?object{
        global $wpdb;$prepared=$args?$wpdb->prepare($sql,...$args):$sql;return $wpdb->get_row($prepared)?:null;
    }
    private function rows(string $sql,mixed ...$args):array{
        global $wpdb;$prepared=$args?$wpdb->prepare($sql,...$args):$sql;return $wpdb->get_results($prepared)?:array();
    }
}
