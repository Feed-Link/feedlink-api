<?php

namespace App\Modules\Upload\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Upload\Requests\UploadPhotoRequest;
use App\Modules\Upload\Services\UploadService;
use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class UploadController extends Controller
{
    public function __construct(
        protected UploadService $uploadService
    ) {}

    public function photo(UploadPhotoRequest $request): JsonResponse
    {
        try {
            $result = $this->uploadService->uploadPhoto($request->file('photo'));

            return $this->success('Photo uploaded successfully', Response::HTTP_CREATED, $result);
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }
}
