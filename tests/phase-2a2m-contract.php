<?php
$root=dirname(__DIR__);
$plugin=file_get_contents($root.'/delnavazan-platform.php');
$migration=file_get_contents($root.'/src/Core/Infrastructure/Migration/Migrator.php');
$service=file_get_contents($root.'/src/Core/Application/CanonicalLessonAuthorityService.php');
$repo=file_get_contents($root.'/src/Core/Infrastructure/Repository/CanonicalLessonAuthorityRepository.php');
$legacy=file_get_contents($root.'/src/Core/Infrastructure/Repository/LessonRepository.php');
foreach(array("DZN_PLATFORM_SCHEMA_VERSION', '20'",'phase2a2m-canonical-lesson-authority-20260917.1','020_canonical_lesson_authority','canonical_term_lesson_v1','dzn_manage_canonical_lessons','canonical_lesson_commands','canonical_lesson_lifecycle_events','019_canonical_enrolment_lifecycle_authority')as$n)if(!str_contains($plugin.$migration.$service.$repo,$n))throw new RuntimeException('Missing Phase M authority invariant: '.$n);
foreach(array('createStandard','createReplacement','expected_teacher_assignment_id','standard_allocation_exhausted','replacement_allocation_exhausted','replacement_origin_already_claimed','stale_teacher_assignment','IdempotencyConflictException','START TRANSACTION','FOR UPDATE','lockRoot','validTransitionContext','canonical_lesson_integrity_conflict')as$n)if(!str_contains($service.$repo.$migration,$n))throw new RuntimeException('Missing canonical Lesson control: '.$n);
foreach(array('LessonScheduleService','wp_amelia','GoogleCalendar','PaymentService','AttendanceService')as$n)if(stripos($service,$n)!==false)throw new RuntimeException('Phase M leaked excluded authority: '.$n);
foreach(array("record_model='legacy_phase1'",'Legacy Lesson mutation target required')as$n)if(!str_contains($legacy,$n))throw new RuntimeException('Legacy Lesson isolation missing: '.$n);
echo "phase-2a2m-contract: pass\n";
