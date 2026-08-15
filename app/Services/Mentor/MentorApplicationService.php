<?php

namespace App\Services\Mentor;

use App\Enums\MentorApplicationSource;
use App\Enums\MentorApplicationStatus;
use App\Models\Company;
use App\Models\MentorProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class MentorApplicationService
{
    public function __construct(
        private readonly MentorCvStorageService $cvStorage
    ) {}

    public function submitSelfApplication(
        User $user,
        array $data,
        UploadedFile $cv
    ): MentorProfile {
        if (
            MentorProfile::query()
                ->where('user_id', $user->id)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'application' => [
                    'You already have a mentor application.',
                ],
            ]);
        }

        $cvPath = $this->cvStorage->store($cv);

        try {
            return DB::transaction(function () use (
                $user,
                $data,
                $cvPath
            ): MentorProfile {
                return MentorProfile::query()->create([
                    'user_id' => $user->id,
                    'submitted_by_user_id' => $user->id,
                    'company_id' => null,
                    'source' => MentorApplicationSource::SelfApplication,
                    'status' => MentorApplicationStatus::Pending,
                    'full_name' => $user->name,
                    'email' => $user->email,
                    'whatsapp_number' => $data['whatsapp_number'],
                    'specialization' => $data['specialization'],
                    'professional_title' => $data['professional_title'],
                    'expertise' => $data['expertise'],
                    'bio' => $data['bio'],
                    'linkedin_url' => $data['linkedin_url'],
                    'github_or_portfolio_url' => $data[
                        'github_or_portfolio_url'
                    ],
                    'cv_path' => $cvPath,
                    'mentoring_topics' => $data['mentoring_topics'],
                    'is_volunteer' => true,
                    'hourly_rate' => null,
                ]);
            });
        } catch (Throwable $exception) {
            $this->cvStorage->delete($cvPath);

            throw $exception;
        }
    }

    public function submitCompanyNomination(
        User $submittedBy,
        Company $company,
        array $data,
        UploadedFile $cv
    ): MentorProfile {
        $email = mb_strtolower(trim($data['email']));

        $alreadyNominated = MentorProfile::query()
            ->where('company_id', $company->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->exists();

        if ($alreadyNominated) {
            throw ValidationException::withMessages([
                'email' => [
                    'This company has already nominated this email.',
                ],
            ]);
        }

        $cvPath = $this->cvStorage->store($cv);

        try {
            return DB::transaction(function () use (
                $submittedBy,
                $company,
                $data,
                $email,
                $cvPath
            ): MentorProfile {
                return MentorProfile::query()->create([
                    'user_id' => null,
                    'submitted_by_user_id' => $submittedBy->id,
                    'company_id' => $company->id,
                    'source' => MentorApplicationSource::CompanyNomination,
                    'status' => MentorApplicationStatus::Pending,
                    'full_name' => trim($data['full_name']),
                    'email' => $email,
                    'whatsapp_number' => $data['whatsapp_number'],
                    'specialization' => $data['specialization'],
                    'professional_title' => $data['professional_title'],
                    'expertise' => $data['expertise'],
                    'bio' => $data['bio'],
                    'linkedin_url' => $data['linkedin_url'],
                    'github_or_portfolio_url' => $data[
                        'github_or_portfolio_url'
                    ],
                    'cv_path' => $cvPath,
                    'mentoring_topics' => $data['mentoring_topics'],
                    'is_volunteer' => true,
                    'hourly_rate' => null,
                ]);
            });
        } catch (Throwable $exception) {
            $this->cvStorage->delete($cvPath);

            throw $exception;
        }
    }
}
