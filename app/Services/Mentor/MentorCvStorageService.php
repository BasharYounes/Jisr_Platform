<?php

namespace App\Services\Mentor;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MentorCvStorageService
{
    private const DISK = 'local';

    private const DIRECTORY = 'mentor-applications/cvs';

    public function store(UploadedFile $file): string
    {
        return $file->store(self::DIRECTORY, self::DISK);
    }

    public function delete(?string $path): void
    {
        if (blank($path)) {
            return;
        }

        Storage::disk(self::DISK)->delete($path);
    }
}
