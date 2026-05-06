<?php

namespace App\Services;


use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class ImageService
{
    public function uploadImage(UploadedFile $file, string $folder = 'profiles'): ?string
    {
        if (!$file || !$file->isValid()) {
            return null;
        }

        return $file->store($folder, 'public');
    }
}
