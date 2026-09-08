<?php
/** Phase 2A.2-C source contract guard; isolated runtime coverage remains deliberately local-only. */
$root = dirname( __DIR__ );
$service = file_get_contents( $root . '/src/Core/Application/TeacherAvailabilityAssentService.php' );
$repository = file_get_contents( $root . '/src/Core/Infrastructure/Repository/TeacherAvailabilityAssentRepository.php' );
$migration = file_get_contents( $root . '/src/Core/Infrastructure/Migration/Migrator.php' );
$privacy = file_get_contents( $root . '/src/Core/Application/BookingRequestPrivacyService.php' );
$controller = file_get_contents( $root . '/src/Admin/Controller/CoordinationCaseController.php' );
$docs = file_get_contents( $root . '/docs/PHASE-2A-2C-TEACHER-AVAILABILITY-ASSENT.md' );
$plugin = file_get_contents( $root . '/delnavazan-platform.php' );

foreach ( array( "DZN_PLATFORM_SCHEMA_VERSION', '9", '009_teacher_availability_assent', 'teacher_availability_assent_snapshots', 'teacher_availability_assents', 'arrangement_fingerprint', 'valid_until', 'review_by', 'ENGINE=InnoDB', 'candidate_fingerprint', 'snapshot_current', 'verify_teacher_availability_assent_schema', 'dzn_manage_teacher_availability_assent', 'dzn_record_own_availability_assent' ) as $needle ) if ( strpos( $migration . $plugin, $needle ) === false ) throw new RuntimeException( 'Missing Phase 2A.2-C schema/capability: ' . $needle );
foreach ( array( 'recordAuthenticatedTeacher', 'recordAdministratorAttestation', 'teacherPrincipalHasAuthority', 'prospective_subject_ref', 'unresolved_course_spec', 'delivery_mode', 'location_scope', 'frequency_per_week', 'expected_duration_minutes', 'schedule_constraints', 'commencement_window_start', 'commencement_window_end', 'timezone', 'arrangement_fingerprint', 'valid_until', 'review_by', 'withdraw', 'invalidate', 'superseded', 'FOR UPDATE', 'changed concurrently', 'currentTeacherReliance' ) as $needle ) if ( strpos( $service . $repository, $needle ) === false ) throw new RuntimeException( 'Missing Teacher Availability Assent contract: ' . $needle );
foreach ( array( 'invalidateForPrivacyErasure', 'teacher_availability_assent.invalidated_privacy_erasure', 'privacy_erased', 'teacher-assent-privacy-erasure' ) as $needle ) if ( strpos( $repository . $privacy, $needle ) === false ) throw new RuntimeException( 'Missing assent privacy-erasure contract: ' . $needle );
foreach ( array( 'check_admin_referer', 'dzn_teacher_assent_record', 'dzn_teacher_assent_state_', 'record_teacher_assent', 'withdraw_teacher_assent', 'invalidate_teacher_assent', 'wp_safe_redirect' ) as $needle ) if ( strpos( $controller, $needle ) === false ) throw new RuntimeException( 'Missing protected Teacher assent surface: ' . $needle );
foreach ( array( 'pre-issue', 'not Student', 'not identity', 'not Student\nidentity', 'capacity reservation', 'Teacher Assignment', 'proposal', 'acceptance', 'Enrolment', 'privacy', 'concurrency', 'erasure' ) as $needle ) if ( stripos( $docs, $needle ) === false ) throw new RuntimeException( 'Missing assent documentation: ' . $needle );
foreach ( array( 'createStudent', 'createLesson', 'platform_payments', 'amelia_', 'wp_amelia', 'register_rest_route', 'booking_reservations', 'teacher_assignments' ) as $forbidden ) if ( stripos( $service . $repository . $controller, $forbidden ) !== false ) throw new RuntimeException( 'Phase 2A.2-C exceeded authority: ' . $forbidden );
echo "Phase 2A.2-C source contract passed\n";
