<?php

namespace App\Enums;

enum ComplaintContextType: string
{
    case ProjectAssignment = 'project_assignment';
    case CompanyTaskAssignment = 'company_task_assignment';
    case OpportunityInterview = 'opportunity_interview';
    case CommunityPost = 'community_post';
    case CommunityComment = 'community_comment';
    case MentorProfile = 'mentor_profile';
}
