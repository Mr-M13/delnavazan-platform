<?php
namespace Delnavazan\Platform\Admin\Controller;
use Delnavazan\Platform\Core\Application\CoreDatasetReadinessService;
use Delnavazan\Platform\Core\Application\EnrolmentConversionService;
use Delnavazan\Platform\Core\Application\TeacherAssignmentService;
use Delnavazan\Platform\Core\Application\CanonicalLessonAuthorityService;
/** Explicit readiness surface; canonical authorities remain the only domain writers. */
final class CoreDatasetReadinessController {
    private const CAP='dzn_view_diagnostics';
    public static function register():void{add_action('admin_menu',array(__CLASS__,'menu'));}
    public static function menu():void{add_submenu_page('dzn-platform','Core dataset readiness','Core dataset readiness',self::CAP,'dzn-core-dataset-readiness',array(__CLASS__,'render'));}
    public static function convertEnrolment(int $arrangementId,string $key,string $reference):array{$r=(new EnrolmentConversionService())->convert($arrangementId,$key);(new CoreDatasetReadinessService())->recordOperation('enrolment_conversion','accepted_service_arrangement',$arrangementId,'operator_entrypoint','staff_record',$reference,$key,array('state'=>'completed','id'=>$r['enrolment_id']));return$r;}
    public static function assignInitialTeacher(int $enrolmentId,string $key,string $reference):array{$r=(new TeacherAssignmentService())->assignInitial($enrolmentId,$key);(new CoreDatasetReadinessService())->recordOperation('initial_teacher_assignment','enrolment',$enrolmentId,'operator_entrypoint','staff_record',$reference,$key,array('state'=>'completed','id'=>$r['assignment_id']));return$r;}
    public static function issueCanonicalLesson(int $termId,int $assignmentId,array $evidence,string $key):array{$r=(new CanonicalLessonAuthorityService())->createStandard($termId,$assignmentId,$evidence,$key);(new CoreDatasetReadinessService())->recordOperation('canonical_lesson_issuance','term',$termId,'operator_entrypoint',(string)($evidence['evidence_channel']??''),(string)($evidence['evidence_reference']??''),$key,array('state'=>'completed','id'=>$r['lesson_id']));return$r;}
    public static function recordCorrection(string $kind,int $id,string $prior,string $corrected,string $reason,string $channel,string $reference,string $note=''):int{return(new CoreDatasetReadinessService())->recordCorrection($kind,$id,$prior,$corrected,$reason,$channel,$reference,$note);}
    public static function render():void{if(!current_user_can(self::CAP))return;echo '<div class="wrap"><h1>Core dataset readiness</h1><p>Read-only reconciliation and evidence entrypoints. No rows are created by this screen.</p><p>Enrolment conversion, initial Teacher Assignment and canonical Lesson issuance remain subject to their existing authorities and capabilities.</p><p>Corrections are append-only evidence and require a separately executed, audited domain command.</p></div>';}
}
