<?php
/*
 * Source/package contract only. It is not a WordPress/MySQL behavioural test.
 * Execute in an isolated PHP environment when PHP is available.
 */
$root = dirname( __DIR__ );
$migration = file_get_contents( $root . '/src/Core/Infrastructure/Migration/Migrator.php' );
$service = file_get_contents( $root . '/src/Core/Application/PrincipalInvitationService.php' );
$repository = file_get_contents( $root . '/src/Core/Infrastructure/Repository/PrincipalInvitationRepository.php' );
$admin = file_get_contents( $root . '/src/Admin/Controller/OnboardingController.php' );
$required = [
    '002_principal_invitation_foundation',
    'teacher_onboarding_states', 'teacher_principal_links', 'student_principal_links',
    'teacher_invitations', 'teacher_invitation_generations',
    'student_account_invitations', 'student_account_invitation_generations',
    'account_claim_attempts', 'platform_audit_events', 'platform_outbox',
    'UNIQUE KEY teacher_id(teacher_id)', 'UNIQUE KEY wordpress_user_id(wordpress_user_id)',
    'secret_digest char(64) NULL', 'delivery_prepared_at', 'delivery_attempt_count',
    'UNIQUE KEY secret_digest(secret_digest)', 'UNIQUE KEY command_key(command_key)',
    'UNIQUE KEY idempotency_key(idempotency_key)', 'ENGINE=InnoDB',
];
foreach ( $required as $fragment ) if ( strpos( $migration, $fragment ) === false ) throw new RuntimeException( 'Missing 2A.0 schema contract: ' . $fragment );
foreach ( ['queued_for_delivery', 'prepareDelivery', 'bin2hex(random_bytes(32))', 'hash_hmac(', 'recovery_required', 'Authenticated principal ownership and recipient binding required', 'Invitation has been superseded', 'Teacher principal link already exists', 'teacher_principal.claim_finalized'] as $fragment ) if ( strpos( $service, $fragment ) === false ) throw new RuntimeException( 'Missing invitation/claim contract: ' . $fragment );
$issue = substr( $service, strpos( $service, 'public function issue' ), strpos( $service, 'public function prepareDelivery' ) - strpos( $service, 'public function issue' ) );
if ( strpos( $issue, 'random_bytes' ) !== false || strpos( $issue, "'secret'=>" ) !== false ) throw new RuntimeException( 'Issuance must not generate or return an invitation secret' );
foreach ( ['supersedeUnclaimedGenerations', 'revokeUnclaimedGenerations', 'cancelPendingDeliveryOutboxes', 'activateQueuedGeneration', 'markDeliveryPrepared', 'deliveryOutboxForGenerationForUpdate'] as $fragment ) if ( strpos( $repository, $fragment ) === false ) throw new RuntimeException( 'Missing delivery recovery contract: ' . $fragment );
foreach ( ['secret_digest', 'delivery_key', 'idempotency_key', 'hasActiveTeacherAuthority', 'FOR UPDATE'] as $fragment ) if ( strpos( $repository, $fragment ) === false ) throw new RuntimeException( 'Missing repository integrity contract: ' . $fragment );
foreach ( ['check_admin_referer', 'dzn_manage_onboarding', 'never rendered, retained, logged, or placed in URLs'] as $fragment ) if ( strpos( $admin, $fragment ) === false ) throw new RuntimeException( 'Missing admin security contract: ' . $fragment );
if ( strpos( $admin, "['secret']" ) !== false ) throw new RuntimeException( 'Admin must not render invitation secrets' );
if ( strpos( $migration, 'booking_requests' ) !== false || strpos( $migration, 'availability_windows' ) !== false ) throw new RuntimeException( '2A.0 must not introduce booking/routing/availability tables' );
echo "Phase 2A.0 source contract passed\n";
