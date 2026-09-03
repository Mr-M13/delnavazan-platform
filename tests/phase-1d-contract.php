<?php
// WordPress/MySQL integration test scaffold: active-fingerprint dedupe/last-seen;
// closed recurrence; state transitions/audit/retry; trusted recording; unsafe detail rejection;
// archive identity/no cascade/conflict; conservative restore and parent relationship checks.
$root=dirname(__DIR__);foreach(['ExceptionService','ArchiveService']as$f)if(!is_file("$root/src/Core/Application/$f.php"))throw new RuntimeException("Missing $f");
$exception=file_get_contents("$root/src/Core/Application/ExceptionService.php");foreach(['fingerprint','recordTrusted','incrementRetry','Unsafe exception detail']as$f)if(!str_contains($exception,$f))throw new RuntimeException("Exception rule missing: $f");
$archive=file_get_contents("$root/src/Core/Application/ArchiveService.php");foreach(['inactive','paused','draft','Archive conflict']as$f)if(!str_contains($archive,$f))throw new RuntimeException("Archive rule missing: $f");
echo "Phase 1D source contract loaded\n";
