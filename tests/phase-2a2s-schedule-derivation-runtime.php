<?php
/**
 * Disposable production-path Phase 2A.2-S schedule-derivation proof (§6.3).
 *
 * Covers: the immediate anchor, local placement in the single shared zone, the half-open send window with
 * its bounded search, the base-anchored expiry for both tiers, the base-plus-count deferral (and its
 * replay), the pre-scheduling NULL exemption, the tier-F strict-before postcondition, and the
 * `schedule_derivation_divergence` refusal of a non-reproducing row.
 */
if(getenv('DZN_PHASE_2A2S_RUNTIME_TEST')!=='schedule'||!defined('WP_CLI')||!WP_CLI||!in_array(wp_get_environment_type(),array('local','development'),true)){fwrite(STDERR,"Phase 2A.2-S schedule runtime refused.\n");exit(1);}
require __DIR__.'/phase-2a2s-fixture.php';
use Delnavazan\Platform\Core\Application\NotificationSchedule;
use Delnavazan\Platform\Core\Application\NotificationSupport;
use Delnavazan\Platform\Core\Application\NotificationReadService;
global $wpdb;$p=$wpdb->prefix.'dzn_';
dzn_s_fix_reset();
wp_set_current_user(1);
$now=gmdate('Y-m-d H:i:s');

// 1. The immediate anchor with no placement or window derives exactly the observation instant.
$ready=dzn_s_fix_ready('TERM_LAPSED','sched-immediate',array(),60,array(array('rule_code'=>'immediate'),array('rule_code'=>'expiry','parameter_a'=>'120')));
$cycle=dzn_s_fix_cycle(null,null,'sched-immediate');
dzn_s_fix_cycle_transition($cycle,'lapsed','lapsed','sched-immediate');
$intent=dzn_s_fix_intent('renewal_cycle',$cycle,'TERM_LAPSED','sched-immediate');
$observed=$ready['service']->observeIntent($intent,array_merge(dzn_s_fix_evidence('sched-immediate'),array('observed_at'=>$now)),dzn_s_fix_key('obs-immediate'));
dzn_s_fix_assert($observed['state']==='scheduled','the notification must schedule');
dzn_s_fix_assert($observed['scheduled_for']===$now,'the immediate anchor must derive the observed instant');
dzn_s_fix_assert($observed['expires_at']===NotificationSupport::addSeconds($now,120*60),'the tier-P expiry must be the base plus the frozen window');
dzn_s_fix_assert($observed['coalesce_bucket']==='','a version without a coalesce rule must carry the empty bucket');

// 2. Local placement resolves in the one shared zone and never moves the agreed local time.
$ready=dzn_s_fix_ready('TERM_LAPSED','sched-placement',array(),60,array(
    array('rule_code'=>'immediate'),array('rule_code'=>'fixed_local_time','ordinal'=>1,'parameter_a'=>'09:00'),
    array('rule_code'=>'fixed_local_time','ordinal'=>2,'parameter_a'=>'recipient_local'),array('rule_code'=>'expiry','parameter_a'=>'1440'),
));
$cycle=dzn_s_fix_cycle(null,null,'sched-placement');
dzn_s_fix_cycle_transition($cycle,'lapsed','lapsed','sched-placement');
$intent=dzn_s_fix_intent('renewal_cycle',$cycle,'TERM_LAPSED','sched-placement');
$placed=$ready['service']->observeIntent($intent,array_merge(dzn_s_fix_evidence('sched-placement'),array('observed_at'=>$now)),dzn_s_fix_key('obs-placement'));
$local=NotificationSupport::utcToLocal('Australia/Brisbane',(string)$placed['scheduled_for']);
dzn_s_fix_assert($local['time']==='09:00','local placement must land on the declared local wall clock');
dzn_s_fix_assert((string)$placed['scheduled_for']>= $now,'placement must never move the instant backwards');

// 3. The send window is half-open and weekday-masked, evaluated in the same shared zone.
$ready=dzn_s_fix_ready('TERM_LAPSED','sched-window',array(),60,array(
    array('rule_code'=>'immediate'),
    array('rule_code'=>'send_window','ordinal'=>1,'parameter_a'=>'09:00'),array('rule_code'=>'send_window','ordinal'=>2,'parameter_a'=>'17:00'),
    array('rule_code'=>'send_window','ordinal'=>3,'parameter_a'=>'62'),array('rule_code'=>'send_window','ordinal'=>4,'parameter_a'=>'academy_local'),
    array('rule_code'=>'expiry','parameter_a'=>'1440'),
));
$cycle=dzn_s_fix_cycle(null,null,'sched-window');
dzn_s_fix_cycle_transition($cycle,'lapsed','lapsed','sched-window');
$intent=dzn_s_fix_intent('renewal_cycle',$cycle,'TERM_LAPSED','sched-window');
$windowed=$ready['service']->observeIntent($intent,array_merge(dzn_s_fix_evidence('sched-window'),array('observed_at'=>$now)),dzn_s_fix_key('obs-window'));
$windowLocal=NotificationSupport::utcToLocal('Australia/Brisbane',(string)$windowed['scheduled_for']);
dzn_s_fix_assert($windowLocal['time']>='09:00'&&$windowLocal['time']<'17:00','the derived instant must sit inside the half-open window');
dzn_s_fix_assert(in_array($windowLocal['weekday'],array(2,3,4,5,6),true),'the derived weekday must be inside the declared mask');

// 4. Deferral is one bounded step of the frozen size, re-derived from the base, and never re-anchors the
//    window; the persisted result reproduces on every replay.
$ready=dzn_s_fix_ready('TERM_LAPSED','sched-defer',array(),60,array(
    array('rule_code'=>'immediate'),array('rule_code'=>'deferral','ordinal'=>1,'parameter_a'=>'60'),array('rule_code'=>'deferral','ordinal'=>2,'parameter_a'=>'3'),
    array('rule_code'=>'expiry','parameter_a'=>'1440'),
));
$cycle=dzn_s_fix_cycle(null,null,'sched-defer');
dzn_s_fix_cycle_transition($cycle,'lapsed','lapsed','sched-defer');
$intent=dzn_s_fix_intent('renewal_cycle',$cycle,'TERM_LAPSED','sched-defer');
$deferred=$ready['service']->observeIntent($intent,array_merge(dzn_s_fix_evidence('sched-defer'),array('observed_at'=>$now)),dzn_s_fix_key('obs-defer'));
$enqueued=$ready['service']->enqueue((int)$deferred['notification_id'],dzn_s_fix_evidence('sched-defer-enqueue'),dzn_s_fix_key('enq-defer'));
dzn_s_fix_assert($enqueued['state']==='queued','the notification must enqueue inside its window');
$moved=$ready['service']->defer((int)$deferred['notification_id'],dzn_s_fix_evidence('sched-defer-1'),dzn_s_fix_key('defer-1'));
dzn_s_fix_assert((int)$moved['deferral_count']===1,'one deferral must move the count by exactly one');
dzn_s_fix_assert((string)$moved['scheduled_for']===NotificationSupport::addSeconds($now,60*60),'the deferred instant must be the base plus one frozen step');
$movedAgain=$ready['service']->defer((int)$deferred['notification_id'],dzn_s_fix_evidence('sched-defer-2'),dzn_s_fix_key('defer-2'));
dzn_s_fix_assert((int)$movedAgain['deferral_count']===2,'a second deferral must move the count again');
dzn_s_fix_assert((string)$movedAgain['scheduled_for']===NotificationSupport::addSeconds($now,120*60),'the second deferred instant must remain base plus count x step, never previous-plus-step from a shifted base');
$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}notifications WHERE id=%d",(int)$deferred['notification_id']));
dzn_s_fix_assert((string)$row->expires_at===NotificationSupport::addSeconds($now,1440*60),'a deferral must never re-anchor the frozen window');
$mirror=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}platform_outbox WHERE notification_id=%d",(int)$deferred['notification_id']));
dzn_s_fix_assert((string)$mirror->scheduled_for===(string)$row->scheduled_for&&(int)$mirror->deferral_count===2,'the outbox mirror must follow the aggregate exactly');
NotificationSchedule::verify(NotificationSchedule::validateComposition(array(
    array('rule_code'=>'immediate','ordinal'=>1),array('rule_code'=>'deferral','ordinal'=>1,'parameter_a'=>'60'),array('rule_code'=>'deferral','ordinal'=>2,'parameter_a'=>'3'),array('rule_code'=>'expiry','ordinal'=>1,'parameter_a'=>'1440'),
),'P'),array(
    'observed_at'=>(string)$row->observed_at,'timezone'=>(string)$row->timezone,'deferral_count'=>(int)$row->deferral_count,
    'schedule_anchor_at'=>(string)$row->schedule_anchor_at,'scheduled_for'=>(string)$row->scheduled_for,'expires_at'=>(string)$row->expires_at,
    'coalesce_bucket'=>'',
),null);

// 5. A deferral that would reach the frozen window closes terminally instead of moving the instant.
$ready=dzn_s_fix_ready('TERM_LAPSED','sched-window-exhausted',array(),60,array(
    array('rule_code'=>'immediate'),array('rule_code'=>'deferral','ordinal'=>1,'parameter_a'=>'1440'),array('rule_code'=>'deferral','ordinal'=>2,'parameter_a'=>'5'),
    array('rule_code'=>'expiry','parameter_a'=>'60'),
));
$cycle=dzn_s_fix_cycle(null,null,'sched-window-exhausted');
dzn_s_fix_cycle_transition($cycle,'lapsed','lapsed','sched-window-exhausted');
$intent=dzn_s_fix_intent('renewal_cycle',$cycle,'TERM_LAPSED','sched-window-exhausted');
$crossing=$ready['service']->observeIntent($intent,array_merge(dzn_s_fix_evidence('sched-cross'),array('observed_at'=>$now)),dzn_s_fix_key('obs-cross'));
$refused=$ready['service']->defer((int)$crossing['notification_id'],dzn_s_fix_evidence('sched-cross-defer'),dzn_s_fix_key('defer-cross'));
dzn_s_fix_assert($refused['state']==='expired'&&$refused['failure_reason_code']==='retry_window_exhausted','a deferral crossing the frozen window must close terminally as window exhaustion');

// 6. The pre-scheduling exemption: a tier-F observation with no durable instant carries both instants NULL.
$ready=dzn_s_fix_ready('AUTOMATIC_RENEWAL_UPCOMING','sched-tier-f-null');
$cycle=dzn_s_fix_cycle(null,null,'sched-tier-f-null');
$intent=dzn_s_fix_intent('renewal_cycle',$cycle,'AUTOMATIC_RENEWAL_UPCOMING','sched-tier-f-null');
$closed=$ready['service']->observeIntent($intent,array_merge(dzn_s_fix_evidence('sched-tier-f-null'),array('observed_at'=>$now)),dzn_s_fix_key('obs-tier-f-null'));
dzn_s_fix_assert($closed['state']==='failed'&&$closed['failure_reason_code']==='tier_f_instant_unavailable','an unavailable tier-F instant must close the observation terminally');
$pendingRow=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}notifications WHERE id=%d",(int)$closed['notification_id']));
dzn_s_fix_assert($pendingRow->scheduled_for===null&&$pendingRow->expires_at===null,'a terminal pre-scheduling row must carry both instants NULL together');

// 7. The tier-F strict-before postcondition: a composition that cannot place the send ahead of the fact
//    it announces closes `expired`/`eligibility_expired` rather than scheduling it.
$ready=dzn_s_fix_ready('GUARANTEE_DEADLINE_APPROACHING','sched-tier-f-strict',array(),60,array(
    array('rule_code'=>'lead_time','parameter_a'=>'1'),array('rule_code'=>'expiry','parameter_a'=>'1440'),
));
$deadline=gmdate('Y-m-d H:i:s',strtotime('+2 minutes'));
$cycle=dzn_s_fix_cycle(null,$deadline,'sched-tier-f-strict');
dzn_s_fix_cycle_transition($cycle,'guarantee_protected','guarantee_protected','sched-tier-f-strict');
$intent=dzn_s_fix_intent('renewal_cycle',$cycle,'GUARANTEE_DEADLINE_APPROACHING','sched-tier-f-strict');
$late=$ready['service']->observeIntent($intent,array_merge(dzn_s_fix_evidence('sched-tier-f-strict'),array('observed_at'=>gmdate('Y-m-d H:i:s',strtotime('+1 minute')))),dzn_s_fix_key('obs-tier-f-strict'));
dzn_s_fix_assert($late['state']==='expired'&&$late['failure_reason_code']==='eligibility_expired','a tier-F derivation that fails the strict postcondition must close expired');

// 8. A rewritten schedule is a divergence, not a reschedule.
$wpdb->update($p.'notifications',array('scheduled_for'=>NotificationSupport::addSeconds($now,9999)),array('id'=>(int)$deferred['notification_id']));
dzn_s_fix_rejected(fn()=>(new NotificationReadService())->one((int)$deferred['notification_id']),'schedule_derivation_divergence','a persisted schedule that no longer reproduces');
echo "Phase 2A.2-S schedule derivation runtime passed\n";
