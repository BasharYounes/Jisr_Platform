<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Opportunity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ChatbotOpportunityMatchingDemoCleanupSeeder extends Seeder
{
    private const COMPANY_WEBSITE = 'https://chatbot-demo.local';

    private const TITLE_PREFIX = '[Chatbot Demo] ';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'ChatbotOpportunityMatchingDemoCleanupSeeder is allowed only in local or testing environments.'
            );
        }

        DB::transaction(function (): void {
            $company = Company::query()
                ->where('website', self::COMPANY_WEBSITE)
                ->first();

            if ($company === null) {
                return;
            }

            Opportunity::query()
                ->where('company_id', $company->id)
                ->where('title', 'like', self::TITLE_PREFIX.'%')
                ->delete();

            $hasOtherOpportunities = Opportunity::query()
                ->where('company_id', $company->id)
                ->exists();

            if (! $hasOtherOpportunities) {
                $company->delete();
            }
        });

        $this->command?->info('Opportunity matching demo data removed successfully.');
    }
}
