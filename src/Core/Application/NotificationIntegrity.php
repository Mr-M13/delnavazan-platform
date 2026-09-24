<?php
namespace Delnavazan\Platform\Core\Application;

/**
 * §7.3/§14 — S's fail-closed integrity proofs over persisted rows.
 *
 * A read model never returns authority from a row it cannot prove: a mutated rule set, a version whose
 * routing/freeze state disagrees with its own state, a notification whose schedule does not reproduce, a
 * closed attempt whose exhaustion shape is wrong, or an outbox mirror that disagrees with its aggregate
 * all raise one of the fixed diagnostics instead of being silently repaired. Nothing here writes.
 */
final class NotificationIntegrity {
    /** The frozen rule-set digest over the ordered `(kind, code, ordinal, parameters)` tuples (§6.2). */
    public static function ruleSetDigest(array $rows):string{
        $canonical=array();
        foreach($rows as $row){
            $canonical[]=array(
                'rule_kind'=>(string)($row['rule_kind']??''),
                'rule_code'=>(string)($row['rule_code']??''),
                'ordinal'=>(int)($row['ordinal']??0),
                'parameter_a'=>$row['parameter_a']===null?null:(string)$row['parameter_a'],
                'parameter_b'=>$row['parameter_b']===null?null:(string)$row['parameter_b'],
                'parameter_c'=>$row['parameter_c']===null?null:(string)$row['parameter_c'],
                'parameter_d'=>$row['parameter_d']===null?null:(string)$row['parameter_d'],
            );
        }
        usort($canonical,static function(array $left,array $right):int{
            return array($left['rule_kind'],$left['rule_code'],$left['ordinal'])<=>array($right['rule_kind'],$right['rule_code'],$right['ordinal']);
        });
        return hash_hmac('sha256','rule_set:'.wp_json_encode($canonical),NotificationSupport::salt());
    }
    /** §9 replay conflict: a same-key/different-payload command never silently rewrites a result. */
    public static function replayPayload(?string $stored,string $expected):void{
        if($stored===null||!hash_equals($stored,$expected))throw new \RuntimeException(NotificationRule::REPLAY_CONFLICT);
    }

    /**
     * §6.1/§7.3 — prove one workflow version's routing, freeze and rule state against itself.
     *
     * @throws \RuntimeException `workflow_version_integrity` or `workflow_rule_set_mutated`.
     */
    public static function versionIntegrity(object $version,array $rules):array{
        $state=(string)$version->state;
        if(!in_array($state,NotificationRule::VERSION_STATES,true))throw new \RuntimeException('workflow_version_integrity');
        $intent=(string)$version->intent_key;
        if(!NotificationRule::registeredIntent($intent)||NotificationRule::unboundIntent($intent))throw new \RuntimeException('workflow_version_integrity');
        $active=$state==='active';
        // An active version is the only row that may hold either routing slot, and it must hold both.
        foreach(array('active_slot','intent_active_slot') as $slot){
            $value=$version->{$slot};
            if($active&&(int)$value!==NotificationRule::ACTIVE_SLOT)throw new \RuntimeException('workflow_version_integrity');
            if(!$active&&$value!==null)throw new \RuntimeException('workflow_version_integrity');
        }
        if($active&&($version->rule_set_digest===null||$version->rule_frozen_at===null))throw new \RuntimeException('workflow_version_integrity');
        if(!$active&&$state==='draft'&&$version->rule_frozen_at!==null)throw new \RuntimeException('workflow_version_integrity');
        // A frozen version's stored rules must reproduce its digest exactly; a post-activation append
        // therefore makes the version un-dispatchable rather than silently wider.
        if($version->rule_set_digest!==null&&!hash_equals((string)$version->rule_set_digest,self::ruleSetDigest($rules)))throw new \RuntimeException('workflow_rule_set_mutated');
        if($active){
            $eligibility=array_values(array_filter($rules,static fn(array $row):bool=>(string)$row['rule_kind']==='eligibility'));
            $schedule=array_values(array_filter($rules,static fn(array $row):bool=>(string)$row['rule_kind']==='schedule'));
            $retry=array_values(array_filter($rules,static fn(array $row):bool=>(string)$row['rule_kind']==='retry'));
            $validated=NotificationEligibility::validateRequiredSet($intent,(string)$version->audience,(string)$version->recipient_kind,$eligibility);
            $composition=NotificationSchedule::validateComposition($schedule,NotificationRule::intentTier($intent));
            NotificationEligibility::assertTierFLeadTime($validated,$composition,NotificationRule::intentTier($intent));
            NotificationRetry::validatePolicy($retry);
            if(NotificationRule::intentTier($intent)==='F')self::tierFSourceAvailable($intent);
        }
        return array('intent'=>$intent,'tier'=>NotificationRule::intentTier($intent),'state'=>$state);
    }
    /**
     * §6.2.4(d) — the tier-F instant source must be durably present before a tier-F version may register
     * or activate. The R2 amendment adds `dzn_renewal_cycles.automatic_charge_at`; while it is absent the
     * refusal is a closed `tier_f_instant_unavailable`, never a deferral or a workaround.
     */
    public static function tierFSourceAvailable(string $intent):void{
        $column=NotificationRule::tierFInstantColumn($intent);
        if($column===null)throw new \RuntimeException('intent_unbound');
        global $wpdb;$prefix=$wpdb->prefix.'dzn_';
        $subject=NotificationRule::SUBJECT_TABLES[NotificationRule::boundAggregate($intent)];
        if(!$wpdb->get_row("SHOW COLUMNS FROM {$prefix}{$subject} LIKE '{$column}'"))throw new \RuntimeException('tier_f_instant_unavailable');
    }
    /**
     * §6.3/§6.5 — the notification's shape: a known state, digest widths, and the locked
     * non-null-with-`scheduled_for` expiry rule with its pre-scheduling exemption.
     *
     * @throws \RuntimeException `workflow_version_integrity` or `schedule_derivation_divergence`.
     */
    public static function notificationShape(object $notification):void{
        $state=(string)$notification->state;
        if(!NotificationRule::notificationState($state))throw new \RuntimeException('workflow_version_integrity');
        foreach(array('notification_key_digest','recipient_digest','subject_reference_digest') as $column){
            $value=(string)$notification->{$column};
            if(!preg_match('/^[0-9a-f]{64}$/',$value))throw new \RuntimeException('workflow_version_integrity');
        }
        $scheduledFor=$notification->scheduled_for;
        $expiresAt=$notification->expires_at;
        if($scheduledFor!==null&&$expiresAt===null)throw new \RuntimeException('schedule_derivation_divergence');
        if($scheduledFor===null&&$expiresAt!==null)throw new \RuntimeException('schedule_derivation_divergence');
        if($scheduledFor!==null&&in_array($state,array('pending'),true))throw new \RuntimeException('schedule_derivation_divergence');
        if($scheduledFor!==null&&$notification->schedule_anchor_at===null)throw new \RuntimeException('schedule_derivation_divergence');
    }
    /**
     * §6.3(f) — prove the persisted derivation reproduces from persisted inputs and the frozen rules.
     *
     * The failure classes are kept distinct so the caller can surface the right diagnostic: an
     * out-of-domain or non-reproducing schedule is `schedule_derivation_divergence`, an expired tier-F
     * postcondition is `eligibility_expired`, and an unavailable instant is `tier_f_instant_unavailable`.
     */
    public static function notificationSchedule(object $notification,array $composition,?string $subjectInstant,array $identity=array()):void{
        self::notificationShape($notification);
        if($notification->scheduled_for===null)return;
        // The coalesce bucket is not a column: it is the §6.3 step 7 function of the persisted anchor and
        // the frozen rule, so the proof is that the frozen logical identity reproduces from it.
        $bucket=NotificationSupport::coalesceBucket((string)$notification->schedule_anchor_at,$composition['coalesce']!==null?(int)$composition['coalesce']['coalesce_window_minutes']:null);
        NotificationSchedule::verify($composition,array(
            'observed_at'=>(string)$notification->observed_at,
            'timezone'=>(string)$notification->timezone,
            'deferral_count'=>(int)$notification->deferral_count,
            'schedule_anchor_at'=>(string)$notification->schedule_anchor_at,
            'scheduled_for'=>(string)$notification->scheduled_for,
            'expires_at'=>(string)$notification->expires_at,
            'coalesce_bucket'=>$bucket,
        ),$subjectInstant);
        if((string)$notification->timezone===''&&$composition['timezone_basis']!==null)throw new \InvalidArgumentException('schedule_derivation_divergence');
        if((string)$notification->timezone!==''&&$composition['timezone_basis']===null)throw new \InvalidArgumentException('schedule_derivation_divergence');
        if($identity!==array()){
            $expected=NotificationSupport::notificationKeyDigest(
                (string)$identity['workflow_key'],(int)$identity['workflow_version'],(string)$identity['intent_key'],
                (string)$identity['audience'],(string)$notification->recipient_digest,
                (string)$notification->subject_aggregate,(int)$notification->subject_aggregate_id,$bucket
            );
            if(!hash_equals((string)$notification->notification_key_digest,$expected))throw new \InvalidArgumentException('schedule_derivation_divergence');
        }
    }
    /**
     * §6.6/§9/§7.3 — prove one closed attempt's exhaustion shape and the notification status it implies.
     *
     * The three rules partition every closed notification by the closing attempt's class and the gate
     * that selected its shape: the terminal vocabulary (with its notification-status condition), the
     * ceiling rule, and the window rule. Each exhaustion shape persists no schedule, and a closure that
     * re-arms must have persisted the deterministic quadruple.
     *
     * @throws \RuntimeException one of `terminal_reason_invalid`, `retry_exhaustion_invalid`,
     *         `retry_window_exhaustion_invalid` or `retry_schedule_divergence`.
     */
    public static function closureIntegrity(object $notification,object $attempt,array $policy):void{
        $failureClass=$attempt->failure_class;
        if($failureClass===null)return;
        if(!NotificationRule::closureClass((string)$failureClass))throw new \RuntimeException('terminal_reason_invalid');
        $outcome=$attempt->outcome_code===null?null:(string)$attempt->outcome_code;
        $reason=$notification->failure_reason_code===null?null:(string)$notification->failure_reason_code;
        $sequence=(int)$attempt->attempt_sequence;
        $maxAttempts=NotificationRetry::maxAttempts($policy);
        $quadruple=array($attempt->applied_jitter_bp,$attempt->base_backoff_seconds,$attempt->backoff_seconds,$attempt->next_available_at);
        $persisted=array_filter($quadruple,static fn($value):bool=>$value!==null)!==array();
        // §6.6 — the fourth partition: an eligibility abort is an audited, controlled refusal of an
        // already-open attempt, never a retry closure. It carries its own closed refusal code identically on
        // the attempt and the notification, closes the notification in the controlled state that code maps
        // to, and derives and persists no retry schedule.
        if((string)$failureClass===NotificationRule::ELIGIBILITY_ABORT_CLASS){
            if($outcome===null||!NotificationRule::eligibilityAbortOutcome($outcome))throw new \RuntimeException('eligibility_abort_invalid');
            if($reason!==$outcome)throw new \RuntimeException('eligibility_abort_invalid');
            if((string)$notification->state!==NotificationRule::controlledState($outcome))throw new \RuntimeException('eligibility_abort_invalid');
            if($persisted)throw new \RuntimeException('eligibility_abort_invalid');
            return;
        }
        // §6.6/§10 — the fifth partition: a terminal command that resolved a live lease closes the attempt
        // `abandoned` carrying the notification's own terminal state as its outcome code, derives no retry
        // schedule and re-arms nothing, so a terminal notification never keeps an open attempt behind it.
        if((string)$failureClass===NotificationRule::LEASE_CANCELLED_CLASS){
            if($outcome===null||!NotificationRule::leaseCancellationOutcome($outcome))throw new \RuntimeException('lease_cancellation_invalid');
            if((string)$notification->state!==$outcome)throw new \RuntimeException('lease_cancellation_invalid');
            if($persisted)throw new \RuntimeException('lease_cancellation_invalid');
            if((string)$attempt->state!=='abandoned')throw new \RuntimeException('lease_cancellation_invalid');
            return;
        }
        if((string)$failureClass==='terminal'){
            // The status is part of the class's rule: a terminal attempt only ever closes the
            // notification as terminal `failed`, so a matching member beside an `expired`, `suppressed`
            // or `cancelled` notification is the forged terminal-class window closure.
            if((string)$notification->state!=='failed')throw new \RuntimeException('terminal_reason_invalid');
            if($reason===null||$outcome===null||!NotificationRule::terminalReason($reason)||!NotificationRule::terminalReason($outcome)||$reason!==$outcome)throw new \RuntimeException('terminal_reason_invalid');
            if($persisted)throw new \RuntimeException('terminal_reason_invalid');
            return;
        }
        $expectedClosure=NotificationRule::nonTerminalClosureCode((string)$failureClass);
        if($outcome!==null&&(NotificationRule::terminalReason($outcome)||$outcome===NotificationRule::CEILING_EXHAUSTION_CODE))throw new \RuntimeException('retry_exhaustion_invalid');
        if($sequence>=$maxAttempts){
            if((string)$notification->state!=='failed'||$reason!==NotificationRule::CEILING_EXHAUSTION_CODE)throw new \RuntimeException('retry_exhaustion_invalid');
            if($persisted)throw new \RuntimeException('retry_exhaustion_invalid');
            return;
        }
        // Below the ceiling a non-terminal closure either re-armed with the persisted quadruple, or is
        // window exhaustion: `expired`/`retry_window_exhausted` with nothing derived and nothing re-armed.
        if($persisted){
            if($expectedClosure!==null&&$outcome!==$expectedClosure&&$outcome!==NotificationRule::LEASE_EXPIRED_OUTCOME)throw new \RuntimeException('retry_schedule_divergence');
            return;
        }
        if((string)$notification->state!=='expired'||$reason!==NotificationRule::WINDOW_EXHAUSTION_CODE)throw new \RuntimeException('retry_window_exhaustion_invalid');
    }
    /**
     * §7.1/§7.3 — the outbox row is a mirror and dispatch index, never an independent derivation root.
     * The mirrored triple must equal the aggregate's, and `available_at` must be exactly one of the two
     * values the mirror contract allows.
     */
    public static function outboxMirror(object $notification,object $row,?string $lastRetryAvailableAt):void{
        if($notification->scheduled_for===null){
            if($row->scheduled_for!==null||$row->expires_at!==null||$row->deferral_count!==null)throw new \RuntimeException('schedule_derivation_divergence');
            return;
        }
        if((string)$row->scheduled_for!==(string)$notification->scheduled_for)throw new \RuntimeException('schedule_derivation_divergence');
        if((string)$row->expires_at!==(string)$notification->expires_at)throw new \RuntimeException('schedule_derivation_divergence');
        if((int)$row->deferral_count!==(int)$notification->deferral_count)throw new \RuntimeException('schedule_derivation_divergence');
        $available=(string)$row->available_at;
        $allowed=array((string)$notification->scheduled_for);
        if($lastRetryAvailableAt!==null)$allowed[]=$lastRetryAvailableAt;
        if(!in_array($available,$allowed,true))throw new \RuntimeException('schedule_derivation_divergence');
        if($available<(string)$notification->scheduled_for)throw new \RuntimeException('schedule_derivation_divergence');
    }
    /** §6.6 — the closed terminal vocabulary, exposed so a caller can normalise without re-declaring it. */
    public static function normaliseTerminalReason(string $reason):string{
        if(!NotificationRule::terminalReason($reason))throw new \InvalidArgumentException('terminal_reason_invalid');
        return $reason;
    }

    /**
     * §6.4 — prove one template version's frozen variable contract against itself.
     *
     * The persisted contract text is re-canonicalised, so only the sorted, deduplicated, allowlisted form
     * round-trips: a drifted column, a digest that does not reproduce from that text, or a required count
     * that disagrees with it fails closed with `template_variable_mismatch`.
     *
     * @throws \RuntimeException `template_variable_mismatch`.
     */
    public static function variableContract(object $version):array{
        $text=$version->variable_contract===null?'':(string)$version->variable_contract;
        $canonical=NotificationSupport::variableContract($text===''?array():explode(',',$text));
        if($canonical['variable_contract']!==$text)throw new \RuntimeException('template_variable_mismatch');
        if((string)$version->variable_contract_digest!==$canonical['variable_contract_digest'])throw new \RuntimeException('template_variable_mismatch');
        if((int)$version->required_variable_count!==$canonical['variable_count'])throw new \RuntimeException('template_variable_mismatch');
        return $canonical;
    }
    /**
     * §6.4 — prove one parameter map against the frozen contract before a snapshot is created.
     *
     * The populated parameter keys must equal the contract exactly — a missing, extra or renamed code is a
     * `template_variable_mismatch` — and the digest is the canonical key-ordered one, so a snapshot can
     * never claim a required code set while encrypting a different parameter map.
     *
     * @throws \RuntimeException `template_variable_mismatch`.
     */
    public static function renderParameters(object $version,array $parameters):array{
        $contract=self::variableContract($version);
        $keys=array_keys($parameters);
        sort($keys,SORT_STRING);
        if($keys!==$contract['variable_codes'])throw new \RuntimeException('template_variable_mismatch');
        return array(
            'contract'=>$contract,'variable_codes'=>$contract['variable_contract'],
            'variable_count'=>$contract['variable_count'],'params_digest'=>NotificationSupport::paramsDigest($parameters),
        );
    }
    /**
     * §6.4 — prove one persisted snapshot against the frozen contract and its decrypted parameter map.
     *
     * The declared code set, the required count, the decrypted parameter key set and the stored
     * `params_digest` must all agree with the contract; a disagreement fails closed with
     * `template_variable_mismatch` rather than dispatching a message whose rendered content cannot be
     * proved. Callers decrypt first, so an unusable cipher stays the distinct `envelope_decrypt_failure`.
     *
     * @throws \RuntimeException `template_variable_mismatch`.
     */
    public static function renderedSnapshot(object $version,object $snapshot,array $parameters):array{
        $proof=self::renderParameters($version,$parameters);
        if($snapshot->variable_codes===null||(string)$snapshot->variable_codes!==$proof['variable_codes'])throw new \RuntimeException('template_variable_mismatch');
        if((int)$snapshot->variable_count!==$proof['variable_count'])throw new \RuntimeException('template_variable_mismatch');
        if($snapshot->params_digest===null||!hash_equals((string)$snapshot->params_digest,$proof['params_digest']))throw new \RuntimeException('template_variable_mismatch');
        return $proof;
    }

    /**
     * §6.2.4 — the persisted, immutable announced instant of one subject row, read exactly as the owning
     * module committed it. A NULL column, an unknown aggregate or an absent row resolves to null; S never
     * recomputes the instant from another authority.
     */
    public static function persistedInstant(string $aggregate,int $aggregateId,?string $column):?string{
        if($column===null||!isset(NotificationRule::SUBJECT_TABLES[$aggregate]))return null;
        global $wpdb;$table=$wpdb->prefix.'dzn_'.NotificationRule::SUBJECT_TABLES[$aggregate];
        $value=$wpdb->get_var($wpdb->prepare("SELECT {$column} FROM {$table} WHERE id=%d",$aggregateId));
        return $value===null?null:(string)$value;
    }

    /**
     * §7.3/§8.4/§9 — the one shared aggregate verification every protected read, dispatch claim and schema
     * verification runs before it proceeds: the persisted derivation reproduces, the mirrored dispatch row
     * agrees with the aggregate (when one is supplied), and every closed attempt's closure partition and
     * persisted retry schedule are proved against the frozen rules. Nothing here writes.
     *
     * @throws \RuntimeException `schedule_derivation_divergence`, `eligibility_expired`,
     *         `tier_f_instant_unavailable`, `terminal_reason_invalid`, `retry_exhaustion_invalid`,
     *         `retry_window_exhaustion_invalid` or `retry_schedule_divergence`.
     */
    public static function aggregateIntegrity(object $notification,array $composition,array $policy,?string $subjectInstant,?object $row,array $attempts,int $workflowVersion,array $identity=array()):void{
        self::notificationSchedule($notification,$composition,$subjectInstant,$identity);
        // §6.6/§7.3: the attempt lifecycle is proved before the closures are partitioned — an attempt whose
        // state vocabulary, event chain, sequence or open/closed shape does not reproduce, or a terminal
        // notification that still holds an open attempt, refuses the read whole.
        self::attemptHistoryIntegrity($notification,$attempts);
        if($row!==null){
            $lastRearm=null;
            foreach($attempts as $attempt)if($attempt->next_available_at!==null)$lastRearm=(string)$attempt->next_available_at;
            self::outboxMirror($notification,$row,$lastRearm);
        }
        foreach($attempts as $attempt){
            if($attempt->finished_at===null)continue;
            self::closureIntegrity($notification,$attempt,$policy);
            self::retryScheduleIntegrity($notification,$attempt,$policy,$workflowVersion);
        }
    }
    /**
     * §6.6/§7.3 — prove one notification's persisted attempt history against the locked lifecycle.
     *
     * Every attempt is accepted only when its `attempt_sequence` is contiguous from 1, its state is in the
     * closed vocabulary, its append-only history is a contiguous chain of legal transitions that ends on
     * that state, its open/closed marker agrees with its state, and no open attempt sits behind a terminal
     * notification. A history that disagrees with the lifecycle is reported as `attempt_lifecycle_invalid`
     * rather than read as authority, so an attempt can never be acknowledged from `leased`, re-handed-off
     * or left open behind a cancelled, expired, suppressed, failed or closed notification.
     *
     * @throws \RuntimeException `attempt_lifecycle_invalid`.
     */
    public static function attemptHistoryIntegrity(object $notification,array $attempts):void{
        global $wpdb;$table=$wpdb->prefix.'dzn_notification_attempt_events';
        $terminal=NotificationRule::terminalNotificationState((string)$notification->state);
        $expected=1;
        foreach($attempts as $attempt){
            if((int)$attempt->attempt_sequence!==$expected)throw new \RuntimeException('attempt_lifecycle_invalid');
            $expected++;
            $state=(string)$attempt->state;
            if(!in_array($state,NotificationRule::ATTEMPT_STATES,true))throw new \RuntimeException('attempt_lifecycle_invalid');
            $open=$attempt->finished_at===null;
            if($open&&!in_array($state,NotificationRule::ATTEMPT_OPEN_STATES,true))throw new \RuntimeException('attempt_lifecycle_invalid');
            if(!$open&&!in_array($state,NotificationRule::ATTEMPT_CLOSED_STATES,true))throw new \RuntimeException('attempt_lifecycle_invalid');
            $events=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE attempt_id=%d ORDER BY event_sequence",(int)$attempt->id))?:array();
            if($events===array())throw new \RuntimeException('attempt_lifecycle_invalid');
            $previous=null;$index=0;
            foreach($events as $event){
                $index++;
                if((int)$event->event_sequence!==$index)throw new \RuntimeException('attempt_lifecycle_invalid');
                $type=(string)$event->event_type;
                if(!in_array($type,NotificationRule::ATTEMPT_EVENT_TYPES,true))throw new \RuntimeException('attempt_lifecycle_invalid');
                $from=$event->from_state===null?null:(string)$event->from_state;
                $to=(string)$event->to_state;
                if($from!==$previous)throw new \RuntimeException('attempt_lifecycle_invalid');
                // A `retry_scheduled` row is the digest-only audit companion of the closure that re-armed:
                // it never moves the attempt, so it may only restate the state it was written in.
                if($type===NotificationRule::RETRY_SCHEDULED_EVENT){
                    if($to!==$from)throw new \RuntimeException('attempt_lifecycle_invalid');
                }elseif(!NotificationRule::legalAttemptTransition($from,$to)){
                    throw new \RuntimeException('attempt_lifecycle_invalid');
                }
                $previous=$to;
            }
            if($previous!==$state)throw new \RuntimeException('attempt_lifecycle_invalid');
            if(!$open)continue;
            // §6.6/§10: exactly one attempt may be open, and only while the aggregate is `dispatching` — a
            // terminal notification keeps no live lease.
            if($terminal||(string)$notification->state!=='dispatching')throw new \RuntimeException('attempt_lifecycle_invalid');
        }
    }
    /**
     * §9 — a closure that persisted a retry schedule must reproduce the deterministic derivation exactly.
     * A closure that persisted nothing (the two exhaustion shapes, and every terminal closure) reproduces
     * nothing; the closure partition has already proved which shape is admissible.
     */
    public static function retryScheduleIntegrity(object $notification,object $attempt,array $policy,int $workflowVersion):void{
        $quadruple=array($attempt->applied_jitter_bp,$attempt->base_backoff_seconds,$attempt->backoff_seconds,$attempt->next_available_at);
        if(array_filter($quadruple,static fn($value):bool=>$value!==null)===array())return;
        // The closure instant is deterministic: the persisted `lease_expires_at` for a lease-expiry closure,
        // the persisted `finished_at` for every other closure, never a fresh clock read.
        $instant=(string)$attempt->outcome_code==='lease_expired'?(string)$attempt->lease_expires_at:(string)$attempt->finished_at;
        NotificationRetry::verifyPersisted($policy,array(
            'attempt_sequence'=>(int)$attempt->attempt_sequence,
            'finished_at'=>$instant,
            'expires_at'=>$notification->expires_at,
            'notification_key_digest'=>(string)$notification->notification_key_digest,
            'workflow_version'=>$workflowVersion,
        ),array(
            'applied_jitter_bp'=>(int)$attempt->applied_jitter_bp,
            'base_backoff_seconds'=>(int)$attempt->base_backoff_seconds,
            'backoff_seconds'=>(int)$attempt->backoff_seconds,
            'next_available_at'=>(string)$attempt->next_available_at,
        ));
    }
}
