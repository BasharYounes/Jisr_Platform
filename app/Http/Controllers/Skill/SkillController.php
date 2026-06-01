<?php

namespace App\Http\Controllers\Skill;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Resources\Skill\SkillResource;
use App\Services\Skill\SkillService;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponse;
class SkillController extends Controller

{
    use ApiResponse;

     public function __construct(
        private readonly SkillService $skillService
    ) {}

   public function index(Request $request): JsonResponse
    {
        $skills = $this->skillService->getAllSkills(
            search: $request->query('search')
        );

        return  $this->success(
            data: SkillResource::collection($skills),
            message: 'Skills retrieved successfully.'
        );
        
    }   
}
