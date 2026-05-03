<?php

namespace App\Services\CV;

use Illuminate\Http\UploadedFile;

class CVStorageService
{
    public function store(UploadedFile $file): string
    {
        return $file->store('cvs', 'public');
    }
}
