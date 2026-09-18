<?php
namespace Delnavazan\Platform\Core\Infrastructure\Repository;

/**
 * Persistence boundary for canonical academy-owed occurrences.
 *
 * An academy obligation is DISTINCT canonical authority: it is not a Phase-M `replacement` Lesson,
 * it never consumes the Student's two-per-Term replacement allowance, and it is not capped by that
 * mechanism. It is bounded to one obligation per source occurrence by `UNIQUE(source_lesson_id)`.
 * Obligations are immutable and independent of Term lifecycle: closing a Term never removes them.
 */
final class CanonicalAcademyObligationRepository {
    private string $p;
    public function __construct(){global $wpdb;$this->p=$wpdb->prefix.'dzn_';}
    public function forLesson(int $lessonId,bool $lock=false):array{global $wpdb;$suffix=$lock?' FOR UPDATE':'';return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->p}canonical_academy_obligations WHERE source_lesson_id=%d ORDER BY id{$suffix}",$lessonId))?:array();}
    public function forTerm(int $termId):array{global $wpdb;return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->p}canonical_academy_obligations WHERE term_id=%d ORDER BY id",$termId))?:array();}
    public function forEnrolment(int $enrolmentId):array{global $wpdb;return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->p}canonical_academy_obligations WHERE enrolment_id=%d ORDER BY id",$enrolmentId))?:array();}
    public function outstandingCount(int $termId):int{global $wpdb;return(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->p}canonical_academy_obligations WHERE term_id=%d AND state='owed'",$termId));}
    public function obligation(int $id,bool $lock=false):?object{global $wpdb;$suffix=$lock?' FOR UPDATE':'';return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->p}canonical_academy_obligations WHERE id=%d{$suffix}",$id));}
    public function insert(array $data):int{
        global $wpdb;
        $old=$wpdb->suppress_errors(true);
        $ok=$wpdb->insert($this->p.'canonical_academy_obligations',$data);
        $error=$wpdb->last_error;
        $wpdb->suppress_errors($old);
        if($ok===false)throw new \RuntimeException('Canonical academy obligation persistence failed: '.$error);
        return(int)$wpdb->insert_id;
    }
    public function duplicate(\Throwable $e):?string{
        if(!preg_match("/Duplicate entry .* for key ['`](?:[^'`.]+\\.)?([^'`]+)['`]/i",$e->getMessage(),$m))return null;
        return strtolower($m[1])==='source_lesson'?'source_lesson':null;
    }
}
