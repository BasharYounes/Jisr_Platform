<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadCVRequest;
use App\Models\CV;
use App\Services\CV\CVStorageService;
use Illuminate\Http\JsonResponse;

class CVController extends Controller
{
    public function __construct(
        private readonly CVStorageService $storageService
    ) {}

    public function upload(UploadCVRequest $request): JsonResponse
    {
        $path = $this->storageService->store($request->file('file'));

        $cv = CV::query()->create([
            'UserId' => $request->user()->id,
            'FileUrl' => $path,
            'IsPrimary' => $request->boolean('is_primary', false),
            'UploadedAt' => now(),
        ]);

        return response()->json([
            'message' => 'CV uploaded successfully.',
            'data' => [
                'cv_id' => $cv->CvID,
                'file_url' => $cv->FileUrl,
                'is_primary' => (bool) $cv->IsPrimary,
            ],
        ], 201);
    }
}
