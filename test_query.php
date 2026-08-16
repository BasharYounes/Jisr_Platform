<?php

use App\Models\CompanyTask;
use App\Models\CompanyTaskApplication;
use App\Models\CompanyTaskAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

$query = CompanyTask::query()
    ->withCount([
        'applications as applicants_count',
        'assignments as accepted_students_count' => function (Builder $query): void {
            $query->where('status', '!=', 'cancelled');
        },
    ])
    ->whereIn('status', [
        'published',
        'in_progress',
    ])
    ->where('deadline', '>=', now())
    ->where(function (Builder $query): void {
        $query->whereNull('max_applicants')
              ->orWhere(
                  CompanyTaskApplication::selectRaw('count(*)')
                      ->whereColumn('company_task_id', 'company_tasks.id'),
                  '<',
                  DB::raw('company_tasks.max_applicants')
              );
    })
    ->where(function (Builder $query): void {
        $query->whereNull('max_accepted_students')
              ->orWhere(
                  CompanyTaskAssignment::selectRaw('count(*)')
                      ->whereColumn('company_task_id', 'company_tasks.id')
                      ->where('status', '!=', 'cancelled'),
                  '<',
                  DB::raw('company_tasks.max_accepted_students')
              );
    });

echo "Query OK. count: " . $query->count() . "\n";
