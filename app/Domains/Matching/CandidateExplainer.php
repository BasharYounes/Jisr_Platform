<?php

namespace App\Domains\Matching;

class CandidateExplainer
{
    /**
     * Build human-readable reasons for a candidate ranking without affecting the score.
     *
     * @param  array{
     *     matched_skills:int,
     *     partially_matched_skills:int,
     *     total_skills:int,
     *     strong_skills:array<int, string>,
     *     project_count:int,
     *     project_score:float,
     *     matched_tags:array<int, string>,
     *     total_tags:int,
     *     activity_points:int,
     *     activity_score:float,
     *     fresh_days:int,
     *     missing_skills:array<int, string>
     * }  $data
     * @return array{reasons: array<int, string>, missing: array<int, string>}
     */
    public function explain(array $data): array
    {
        $reasons = [];

        if ($data['total_skills'] > 0) {
            $reasons[] = sprintf(
                'يطابق الطالب %d من أصل %d مهارة مطلوبة بشكل كامل، مع %d مطابقة جزئية. | The student fully matches %d of %d required skills, with %d partial matches.',
                $data['matched_skills'],
                $data['total_skills'],
                $data['partially_matched_skills'],
                $data['matched_skills'],
                $data['total_skills'],
                $data['partially_matched_skills']
            );
        } else {
            $reasons[] = 'لا توجد مهارات مطلوبة مسجلة لهذه الفرصة. | No required skills are registered for this opportunity.';
        }

        foreach ($data['strong_skills'] as $skill) {
            $reasons[] = "يمتلك الطالب مستوى قويًا في {$skill}. | Strong proficiency in {$skill}.";
        }

        if ($data['project_count'] > 0) {
            $reasons[] = sprintf(
                'متوسط تقييم مشاريع الطالب النهائية %.2f/100 عبر %d تقييمات. | Final project evaluation average is %.2f/100 across %d evaluations.',
                $data['project_score'],
                $data['project_count'],
                $data['project_score'],
                $data['project_count']
            );
        } else {
            $reasons[] = 'لا توجد تقييمات مشاريع نهائية متاحة للطالب. | No finalized project evaluations are available for this student.';
        }

        if ($data['total_tags'] > 0) {
            $reasons[] = sprintf(
                'يطابق الطالب %d من أصل %d وسوم مرتبطة بالفرصة. | The student matches %d of %d opportunity tags.',
                count($data['matched_tags']),
                $data['total_tags'],
                count($data['matched_tags']),
                $data['total_tags']
            );
        }

        $reasons[] = sprintf(
            'رصيد التفاعل المحتسب: %d نقطة، بدرجة تفاعل %.2f/100. | Counted interaction balance: %d points, activity score %.2f/100.',
            $data['activity_points'],
            $data['activity_score'],
            $data['activity_points'],
            $data['activity_score']
        );

        if ($data['fresh_days'] <= 30) {
            $reasons[] = sprintf(
                'آخر تحديث لنشاط الحساب منذ %d يومًا. | Account activity was last updated %d days ago.',
                $data['fresh_days'],
                $data['fresh_days']
            );
        } else {
            $reasons[] = 'لا توجد حداثة نشاط ضمن نافذة الثلاثين يومًا الأخيرة. | No freshness credit is available within the last 30 days.';
        }

        return [
            'reasons' => $reasons,
            'missing' => array_values($data['missing_skills']),
        ];
    }
}
