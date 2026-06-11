<?php

namespace App\Services\User;

use App\Interfaces\StudentRepositoryInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(
        protected StudentRepositoryInterface $studentRepository,
        protected UserRepositoryInterface $userRepository,
    ) {}

    // ====================
    // === Company
    // ====================
    public function editCompanyProfile($user, $company, array $data, $request)
    {
        if (! $company) {
            throw ValidationException::withMessages([
                'company' => ['Company profile not found.'],
            ]);
        }

        $userData = collect($data)->only([
            'name',
            'bio',
            'email',
        ])->toArray();

        $companyData = collect($data)->only([
            'industry',
            'location',
            'website',
        ])->toArray();

        if ($request->hasFile('profile_picture_url')) {
            $path = $request->file('profile_picture_url')
                ->store('profiles', 'public');

            $userData['profile_picture_url'] = $path;
        }

        if ($request->hasFile('documentation_file')) {
            $path = $request->file('documentation_file')
                ->store('companies/documentations', 'public');

            $companyData['documentation_file'] = $path;
        }

        if (! empty($userData)) {
            $user->update($userData);
        }

        if (! empty($companyData)) {
            $company->update($companyData);
        }

        return $company->fresh();
    }

    // ====================
    // === student
    // ====================

    public function editStudentProfile(User $user, array $data, ?UploadedFile $profilePicture = null): StudentProfile
    {
        return DB::transaction(function () use ($user, $data, $profilePicture) {
            $studentProfile = $this->getStudentProfileOrFail($user);

            $this->updateUserProfile($user, $data, $profilePicture);

            $this->updateStudentProfile($studentProfile, $data);

            return $studentProfile->fresh('user');
        });
    }

    private function getStudentProfileOrFail(User $user): StudentProfile
    {
        $studentProfile = $this->studentRepository->findByUser($user);

        if (! $studentProfile) {
            throw ValidationException::withMessages([
                'student_profile' => ['Student profile not found.'],
            ]);
        }

        return $studentProfile;
    }

    private function updateUserProfile(
        User $user,
        array $data,
        ?UploadedFile $profilePicture = null): void
    {
        $userData = $this->extractUserData($data);

        if ($profilePicture) {
            $userData['profile_picture_url'] = $profilePicture->store('profiles', 'public');
        }

        if (! empty($userData)) {
            $this->userRepository->update($user, $userData);
        }
    }

    private function updateStudentProfile(StudentProfile $studentProfile, array $data): void
    {
        $studentProfileData = $this->extractStudentProfileData($data);

        if (! empty($studentProfileData)) {
            $this->studentRepository->update($studentProfile, $studentProfileData);
        }
    }

    private function extractUserData(array $data): array
    {
        return collect($data)
            ->only([
                'name',
                'bio',
                'email',
            ])
            ->toArray();
    }

    private function extractStudentProfileData(array $data): array
    {
        return collect($data)
            ->only([
                'university',
                'major',
                'graduation_year',
                'phone',
            ])
            ->toArray();
    }

    public function getStudentProfile(User $user): ?StudentProfile
    {
        return $this->studentRepository->findByUser($user);
    }
}
