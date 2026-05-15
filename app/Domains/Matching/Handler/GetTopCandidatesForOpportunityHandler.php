<?php

namespace App\Domain\Matching\Handlers;

use App\Domain\Matching\CandidateExplainer;
use Illuminate\Support\Facades\DB;
use App\Domain\Matching\Queries\GetTopCandidatesForOpportunity;

class GetTopCandidatesForOpportunityHandler
{
    public function handle(GetTopCandidatesForOpportunity $query)
    {
        $opportunityId = $query->opportunityId;



        $candidates = DB::table('users as u')
            ->select(
                'u.UserID',
                DB::raw('SUM(COALESCE(us.ProficiencyLevel, 0) * os.Weight) as skill_score'),
                DB::raw('MIN(
                    CASE
                        WHEN os.Mandatory = true AND us.SkillId IS NULL THEN 0
                        ELSE 1
                    END
                ) as mandatory_ok')
            )
            ->crossJoin('OpportunitySkill as os')
            ->leftJoin('UserSkill as us', function ($join) {
                $join->on('us.UserId', '=', 'u.UserID')
                     ->on('us.SkillId', '=', 'os.SkillId');
            })
            ->where('u.IsActive', true)
            ->where('os.OpportunityId', $opportunityId)
            ->groupBy('u.UserID')
            ->having('mandatory_ok', 1)
            ->get();

        return $candidates->map(function ($candidate) use ($opportunityId) {
            $explainer = new CandidateExplainer();

            $projectScore = $this->getProjectScore($candidate->UserID);
            $activityScore = $this->getActivityScore($candidate->UserID);
            $tagScore = $this->getTagScore($candidate->UserID, $opportunityId);
            $freshness = $this->getFreshnessScore($candidate->UserID);
            $missingSkills = $this->getMissingSkills($candidate->UserID, $opportunityId);
            $strongSkills = $this->getStrongSkills($candidate->UserID, $opportunityId);

            $finalScore =
                $candidate->skill_score * 0.55 +
                $projectScore * 0.20 +
                $tagScore * 0.10 +
                $activityScore * 0.10 +
                $freshness * 0.05;

                $details = [
                'matched_skills' => $this->getMatchedSkillsCount($candidate->UserID, $opportunityId),
                'strong_skills' => $strongSkills,
                'project_count' => $this->getProjectCount($candidate->UserID),
                'project_score' => $projectScore,
                'fresh_days' => $this->getFreshDays($candidate->UserID),
                'missing_skills' => $missingSkills,
                ];

            $explanation = $explainer->explain($details);

            return [
                'user_id' => $candidate->UserID,
                'skill_score' => $candidate->skill_score,
                'project_score' => $projectScore,
                'activity_score' => $activityScore,
                'tag_score' => $tagScore,
                'freshness' => $freshness,
                'final_score' => round($finalScore, 2),
            ];
        })
        ->sortByDesc('final_score')
        ->take($query->limit)
        ->values();
    }

    //  -------------------------   Scoring Functions   --------------------------------

    private function getProjectScore($userId)
    {
        return DB::table('ProjectEvaluation')
            ->where('UserId', $userId)
            ->avg('TotalScore') ?? 0;
    }

    private function getActivityScore($userId)
    {
        $points = DB::table('PointTransaction')
            ->where('UserID', $userId)
            ->sum('Points');

        return log($points + 1);
    }

    private function getTagScore($userId, $opportunityId)
    {
        $matchCount = DB::table('UserTag as ut')
            ->join('OpportunityTag as ot', 'ot.TagID', '=', 'ut.TagID')
            ->where('ut.UserID', $userId)
            ->where('ot.OpportunityID', $opportunityId)
            ->count();

        return $matchCount;
    }

    private function getFreshnessScore($userId)
    {
        $days = $this->getFreshDays($userId);

        return max(0, 30 - $days) / 30;
    }

    private function getMissingSkills($userId, $opportunityId)
    {
        $requiredSkills = DB::table('OpportunitySkill')
            ->where('OpportunityId', $opportunityId)
            ->pluck('SkillId')
            ->toArray();

        $userSkills = DB::table('UserSkill')
            ->where('UserId', $userId)
            ->pluck('SkillId')
            ->toArray();

        return array_diff($requiredSkills, $userSkills);
    }

    private function getStrongSkills($userId, $opportunityId)
    {
        return DB::table('UserSkill as us')
            ->join('OpportunitySkill as os', 'os.SkillId', '=', 'us.SkillId')
            ->where('us.UserId', $userId)
            ->where('os.OpportunityId', $opportunityId)
            ->where('us.ProficiencyLevel', '>=', 4)
            ->pluck('us.SkillId')
            ->toArray();
    }

    // It needs to be improved to ensure that projects include the required skills,
    // but for simplicity we just count them here
    private function getProjectCount($userId)
    {
        return DB::table('ProjectEvaluation')
            ->where('UserId', $userId)
            ->count();
    }

    private function getFreshDays($userId)
    {
        $lastActivity = DB::table('users')
            ->where('UserID', $userId)
            ->value('UpdatedAt');

        if (!$lastActivity) return 999;

        return now()->diffInDays($lastActivity);
    }

    private function getMatchedSkillsCount($userId, $opportunityId)
    {
        return DB::table('UserSkill as us')
            ->join('OpportunitySkill as os', 'os.SkillId', '=', 'us.SkillId')
            ->where('us.UserId', $userId)
            ->where('os.OpportunityId', $opportunityId)
            ->count();
    }

}
