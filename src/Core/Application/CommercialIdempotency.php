<?php
namespace Delnavazan\Platform\Core\Application;

/** Digest-only Phase-R1 commercial command and evidence boundary; raw keys and references never persist. */
final class CommercialIdempotency {
    public static function key(string $key):string{
        if(trim($key)==='')throw new \InvalidArgumentException('Idempotency key required');
        return hash_hmac('sha256','commercial_key:'.$key,wp_salt('dzn_commercial'));
    }
    public static function payload(array $payload):string{
        ksort($payload);
        return hash_hmac('sha256',wp_json_encode($payload,JSON_UNESCAPED_SLASHES),wp_salt('dzn_commercial'));
    }
    /** Evidence references, provider references and obligation references persist only as keyed digests. */
    public static function evidence(string $reference):string{
        if(trim($reference)==='')throw new \InvalidArgumentException('Evidence reference required');
        return hash_hmac('sha256','commercial_evidence:'.$reference,wp_salt('dzn_commercial'));
    }
    /** A provider payment reference must be reconstructible for reconciliation without storing the raw value. */
    public static function providerReference(string $providerKey,string $reference):string{
        if(trim($providerKey)===''||trim($reference)==='')throw new \InvalidArgumentException('Provider evidence reference required');
        return hash_hmac('sha256','commercial_provider:'.$providerKey.':'.$reference,wp_salt('dzn_commercial'));
    }
    public static function obligationReference(string $reference):string{
        if(trim($reference)==='')throw new \InvalidArgumentException('Obligation reference required');
        return hash_hmac('sha256','commercial_obligation:'.$reference,wp_salt('dzn_commercial'));
    }
    public static function promotionCode(string $code):string{
        if(trim($code)==='')throw new \InvalidArgumentException('Promotion code required');
        return hash_hmac('sha256','commercial_promotion_code:'.strtoupper(trim($code)),wp_salt('dzn_commercial'));
    }
    /** Stable reconciliation fingerprint for one commercial exception class. */
    public static function fingerprint(string $reasonCode,string $scope,string $value):string{
        return hash('sha256',implode("\n",array($reasonCode,$scope,$value)));
    }
}
