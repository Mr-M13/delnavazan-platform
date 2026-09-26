<?php
namespace Delnavazan\Platform\Portals;

final class PortalRateLimiter {
    public function allow(string $surface,string $fingerprint,int $limit=30,int $window=60):bool {
        $key='dzn_portal_rate_'.hash('sha256',$surface.'|'.$fingerprint);$count=(int)get_transient($key);
        if($count>=$limit)return false;set_transient($key,$count+1,$window);return true;
    }
}
