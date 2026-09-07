<?php
namespace Delnavazan\Platform\Core\Application;

use Delnavazan\Platform\Core\Infrastructure\Repository\BookingRequestRepository;
use Delnavazan\Platform\Core\Infrastructure\Repository\BookingRequestIntakeRepository;
use Delnavazan\Platform\Core\Application\ExceptionService;

/** High-trust privacy action; it closes request authority before clearing PII. */
final class BookingRequestPrivacyService {
    public function __construct(private ?BookingRequestRepository $requests=null, private ?BookingRequestIntakeRepository $intake=null) { $this->requests ??= new BookingRequestRepository(); $this->intake ??= new BookingRequestIntakeRepository(); }
    public function erase(int $requestId, int $actorId, string $reason): void {
        if (!preg_match('/^[a-z0-9_]{3,64}$/D',$reason)) throw new \InvalidArgumentException('Invalid erasure reason');
        $now=gmdate('Y-m-d H:i:s'); $this->requests->begin(); try { $request=$this->intake->requestForUpdate($requestId); if(!$request || $request->student_id!==null || $request->lifecycle_status!=='submitted' || $request->resolution_state!=='unresolved')throw new \InvalidArgumentException('Booking Request cannot be erased'); $this->intake->eraseRequestPii($requestId,$actorId,$reason,BookingRequestIdempotency::tombstoneDigest($requestId,$now),$now); (new ExceptionService())->removeTrustedBookingRequestMatchAttention($requestId); $this->requests->commit(); } catch(\Throwable $e){$this->requests->rollback();throw $e;}
    }
}
