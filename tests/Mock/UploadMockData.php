<?php

namespace Tests\Mock;

use Illuminate\Http\UploadedFile;

class UploadMockData
{
    /**
     * A fake image upload.
     */
    public static function imageFile(string $name = 'photo.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 20, 20);
    }

    /**
     * A fake document upload.
     */
    public static function documentFile(string $name = 'document.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 10, 'application/pdf');
    }
}
