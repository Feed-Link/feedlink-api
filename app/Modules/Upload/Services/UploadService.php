<?php

namespace App\Modules\Upload\Services;

use App\Modules\Core\Enums\CloudinaryFolder;
use Cloudinary\Cloudinary;
use Exception;
use Illuminate\Http\UploadedFile;

class UploadService
{
    private Cloudinary $cloudinary;

    public function __construct()
    {
        $this->cloudinary = new Cloudinary;
    }

    /**
     * Upload a photo to Cloudinary and return the secure URL and public ID.
     *
     * @return array{url: string, public_id: string}
     */
    public function uploadPhoto(UploadedFile $file, CloudinaryFolder $folder = CloudinaryFolder::LISTINGS): array
    {
        $result = $this->cloudinary->uploadApi()->upload($file->getRealPath(), [
            'folder' => $folder->value,
            'resource_type' => 'image',
        ]);

        if (empty($result['secure_url'])) {
            throw new Exception('Photo upload failed', 500);
        }

        return [
            'url' => $result['secure_url'],
            'public_id' => $result['public_id'],
        ];
    }
}
