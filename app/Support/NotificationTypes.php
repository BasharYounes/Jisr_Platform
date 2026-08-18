<?php

namespace App\Support;

final class NotificationTypes
{
    public const PROJECT_ASSIGNED = 'project_assigned';

    public const PROJECT_STATUS_CHANGED = 'project_status_changed';

    public const PROJECT_SUBMITTED = 'project_submitted';

    public const PROJECT_REVISION_REQUESTED = 'project_revision_requested';

    public const PROJECT_EVALUATED = 'project_evaluated';

    public const PROJECT_APPROVED = 'project_approved';

    public const PROJECT_MESSAGE_RECEIVED = 'project_message_received';

    // Company Tasks
    public const COMPANY_TASK_APPLICATION_ACCEPTED = 'company_task_application_accepted';

    public const COMPANY_TASK_HIGH_MATCH_APPLICATION = 'company_task_high_match_application';

    public const COMPANY_TASK_SUBMISSION_RECEIVED = 'company_task_submission_received';

    // Company Opportunities
    public const COMPANY_OPPORTUNITY_HIGH_MATCH_APPLICATION = 'company_opportunity_high_match_application';
}
