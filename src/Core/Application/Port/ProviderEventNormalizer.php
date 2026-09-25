<?php
namespace Delnavazan\Platform\Core\Application\Port;

/**
 * Provider-event boundary.
 *
 * The normaliser verifies inbound authenticity over the exact raw body and translates one provider
 * event into provider-neutral facts. It never writes Phase-O or Phase-P storage directly and never
 * supplies a resolved canonical participant or a verification assertion: the adapter supplies a
 * provider account reference and a role, and Phase P resolves identity from its own registry.
 */
interface ProviderEventNormalizer {
    /**
     * @param array{provider_code:string,raw_body:string,headers:array<string,string>} $delivery
     */
    public function verify(array $delivery):bool;

    /**
     * @param array{provider_code:string,raw_body:string} $delivery
     * @return array{provider_code:string,provider_event_key:string,provider_payload_key:string,participant_role:string,provider_account_key:string,observed_at:string,join_at_utc:?string,leave_at_utc:?string,lesson_id:?int,schedule_version_id:?int,provenance_reference:string,evidence_reference:string}
     */
    public function normalise(array $delivery):array;
}
