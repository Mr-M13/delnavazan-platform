<?php
namespace Delnavazan\Platform\Core\Application;

/**
 * Phase 2A.2-R2 fail-closed aggregate proof for the read models.
 *
 * Every R2 aggregate is an append-only event history plus one mutable current row. A read model may
 * only return that aggregate as authority when the history *and* the row agree with each other and
 * with the locked rule table: gap-free contiguous sequences, a leading null `from_state`, a chain in
 * which every event continues where the previous one ended, only legal transitions and event types,
 * the final event agreeing with the recorded current state, and a recorded aggregate version equal to
 * the number of events that advanced it (every event except the version-neutral `extended`). A
 * malformed aggregate is refused, never repaired and never presented as authority.
 */
final class RecurringIntegrity {
    private const VERSION_COLUMNS=array(
        'recurring_enrolment'=>'recurring_enrolment_version',
        'renewal_cycle'=>'renewal_cycle_version',
        'collection_intent'=>'collection_intent_version',
        'recovery_case'=>'recovery_case_version',
        'refund_review'=>'refund_review_version',
        'recurring_protection'=>'recurring_protection_version',
    );

    /**
     * Prove one stored aggregate against its own append-only history before it is read as authority.
     *
     * @param string $aggregate R2 aggregate name (the same vocabulary the command guards use)
     * @param object $row the stored current row, carrying its recorded version column
     * @param array  $events the stored history, ordered by `event_sequence`
     */
    public static function aggregate(string $aggregate,object $row,array $events,string $reason):void{
        $versionColumn=self::VERSION_COLUMNS[$aggregate]??null;
        if($versionColumn===null)throw new \InvalidArgumentException('Controlled recurring aggregate required');
        $states=RecurringRule::aggregateStates($aggregate);
        $eventTypes=RecurringRule::aggregateEventTypes($aggregate);
        $version=(int)($row->{$versionColumn}??0);
        if($version<1||$events===array())throw new \RuntimeException($reason);
        $previous=null;
        $advancing=0;
        foreach($events as $index=>$event){
            $sequence=(int)($event->event_sequence??0);
            $type=(string)($event->event_type??'');
            $from=$event->from_state===null?null:(string)$event->from_state;
            $to=(string)($event->to_state??'');
            if($sequence!==$index+1)throw new \RuntimeException($reason);
            if(!in_array($type,$eventTypes,true))throw new \RuntimeException($reason);
            if(!in_array($to,$states,true))throw new \RuntimeException($reason);
            if($from!==null&&!in_array($from,$states,true))throw new \RuntimeException($reason);
            // The chain is gap-free: the first event opens the aggregate and every later event continues
            // from the state its predecessor recorded.
            if($from!==$previous)throw new \RuntimeException($reason);
            if(!RecurringRule::legalTransition($aggregate,$from,$to))throw new \RuntimeException($reason);
            if($from===$to&&!RecurringRule::sameStateEventType($type))throw new \RuntimeException($reason);
            if(!RecurringRule::versionNeutralEventType($type))$advancing++;
            $previous=$to;
        }
        // The row is the projection of its history: the last event decides the current state, and the
        // recorded version counts exactly the events that advanced it.
        if($previous!==(string)($row->state??''))throw new \RuntimeException($reason);
        if($advancing!==$version)throw new \RuntimeException($reason);
    }
}
