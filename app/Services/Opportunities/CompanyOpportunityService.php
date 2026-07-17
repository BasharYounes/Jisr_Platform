<?php

namespace App\Services\Opportunities;

use App\Interfaces\CompanyOpportunityRepositoryInterface;
use App\Models\Opportunity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyOpportunityService
{
    public function __construct(
        private readonly CompanyOpportunityRepositoryInterface $companyOpportunityRepository
    ) {}

    public function getCompanyOpportunities(
        int $companyId,
        ?string $status = null,
        ?string $type = null,
        ?string $search = null
    ): Collection {
        return $this->companyOpportunityRepository->getByCompany(
            companyId: $companyId,
            status: $status,
            type: $type,
            search: $search
        );
    }

    public function createOpportunity(
        int $companyId,
        array $data
    ): Opportunity {
        return DB::transaction(function () use ($companyId, $data): Opportunity {
            $skills = $data['skills'] ?? [];

            unset($data['skills']);

            $opportunity = $this->companyOpportunityRepository->create([
                ...$data,
                'company_id' => $companyId,
                'status' => 'draft',
                'posted_at' => null,
            ]);

            if (! empty($skills)) {
                $this->companyOpportunityRepository->syncSkills(
                    opportunity: $opportunity,
                    skills: $skills
                );
            }

            return $opportunity->fresh([
                'company',
                'skills',
            ]);
        });
    }

    public function getCompanyOpportunityDetails(
        int $companyId,
        int $opportunityId
    ): Opportunity {
        return $this->companyOpportunityRepository
            ->findCompanyOpportunityOrFail(
                companyId: $companyId,
                opportunityId: $opportunityId
            );
    }

    public function updateOpportunity(
        int $companyId,
        int $opportunityId,
        array $data
    ): Opportunity {
        return DB::transaction(function () use (
            $companyId,
            $opportunityId,
            $data
        ): Opportunity {
            $opportunity = $this->companyOpportunityRepository
                ->findCompanyOpportunityOrFail(
                    companyId: $companyId,
                    opportunityId: $opportunityId
                );

            $this->ensureOpportunityCanBeUpdated(
                opportunity: $opportunity,
                data: $data
            );

            $skills = $data['skills'] ?? null;

            unset($data['skills']);

            if ($opportunity->status === 'published') {
                $data = Arr::only($data, [
                    'title',
                    'description',
                    'location',
                    'salary_min',
                    'salary_max',
                    'deadline',
                ]);
            }

            $updatedOpportunity = $this->companyOpportunityRepository->update(
                opportunity: $opportunity,
                data: $data
            );

            if ($skills !== null && $opportunity->status === 'draft') {
                $this->companyOpportunityRepository->syncSkills(
                    opportunity: $updatedOpportunity,
                    skills: $skills
                );
            }

            return $updatedOpportunity->fresh([
                'company',
                'skills',
            ]);
        });
    }

    public function publishOpportunity(
        int $companyId,
        int $opportunityId
    ): Opportunity {
        return DB::transaction(function () use (
            $companyId,
            $opportunityId
        ): Opportunity {
            $opportunity = $this->companyOpportunityRepository
                ->findCompanyOpportunityOrFail(
                    companyId: $companyId,
                    opportunityId: $opportunityId
                );

            $this->ensureOpportunityCanBePublished($opportunity);

            return $this->companyOpportunityRepository->publish($opportunity);
        });
    }

    public function closeOpportunity(
        int $companyId,
        int $opportunityId
    ): Opportunity {
        return DB::transaction(function () use (
            $companyId,
            $opportunityId
        ): Opportunity {
            $opportunity = $this->companyOpportunityRepository
                ->findCompanyOpportunityOrFail(
                    companyId: $companyId,
                    opportunityId: $opportunityId
                );

            if ($opportunity->status !== 'published') {
                throw ValidationException::withMessages([
                    'opportunity' => [
                        'يمكن إغلاق الفرصة فقط عندما تكون منشورة. | Only published opportunities can be closed.',
                    ],
                ]);
            }

            return $this->companyOpportunityRepository->close($opportunity);
        });
    }

    public function cancelOpportunity(
        int $companyId,
        int $opportunityId
    ): Opportunity {
        return DB::transaction(function () use (
            $companyId,
            $opportunityId
        ): Opportunity {
            $opportunity = $this->companyOpportunityRepository
                ->findCompanyOpportunityOrFail(
                    companyId: $companyId,
                    opportunityId: $opportunityId
                );

            if (! in_array($opportunity->status, ['draft', 'published'], true)) {
                throw ValidationException::withMessages([
                    'opportunity' => [
                        'يمكن إلغاء الفرصة فقط عندما تكون Draft أو Published. | Only draft or published opportunities can be cancelled.',
                    ],
                ]);
            }

            return $this->companyOpportunityRepository->cancel($opportunity);
        });
    }

    public function deleteOpportunity(
        int $companyId,
        int $opportunityId
    ): void {
        DB::transaction(function () use ($companyId, $opportunityId): void {
            $opportunity = $this->companyOpportunityRepository
                ->findCompanyOpportunityOrFail(
                    companyId: $companyId,
                    opportunityId: $opportunityId
                );

            if ($opportunity->status !== 'draft') {
                throw ValidationException::withMessages([
                    'opportunity' => [
                        'يمكن حذف الفرصة فقط وهي Draft. استخدم Cancel للفرص المنشورة. | Only draft opportunities can be deleted. Use cancel for published opportunities.',
                    ],
                ]);
            }

            $this->companyOpportunityRepository->delete($opportunity);
        });
    }

    private function ensureOpportunityCanBeUpdated(
        Opportunity $opportunity,
        array $data
    ): void {
        if (! in_array($opportunity->status, ['draft', 'published'], true)) {
            throw ValidationException::withMessages([
                'opportunity' => [
                    'لا يمكن تعديل فرصة مغلقة أو ملغاة. | Closed or cancelled opportunities cannot be updated.',
                ],
            ]);
        }

        if (
            $opportunity->status === 'published'
            && (
                array_key_exists('type', $data)
                || array_key_exists('skills', $data)
            )
        ) {
            throw ValidationException::withMessages([
                'opportunity' => [
                    'لا يمكن تعديل النوع أو المهارات بعد نشر الفرصة. | Type and skills cannot be updated after publishing.',
                ],
            ]);
        }
    }

    private function ensureOpportunityCanBePublished(
        Opportunity $opportunity
    ): void {
        if ($opportunity->status !== 'draft') {
            throw ValidationException::withMessages([
                'opportunity' => [
                    'يمكن نشر الفرصة فقط عندما تكون Draft. | Only draft opportunities can be published.',
                ],
            ]);
        }

        if ($opportunity->skills()->count() === 0) {
            throw ValidationException::withMessages([
                'skills' => [
                    'يجب إضافة مهارة واحدة على الأقل قبل نشر الفرصة. | At least one required skill is needed before publishing.',
                ],
            ]);
        }

        if (
            $opportunity->deadline !== null
            && Carbon::parse($opportunity->deadline)->isPast()
        ) {
            throw ValidationException::withMessages([
                'deadline' => [
                    'موعد انتهاء التقديم يجب أن يكون في المستقبل. | Deadline must be in the future before publishing.',
                ],
            ]);
        }
    }
}
