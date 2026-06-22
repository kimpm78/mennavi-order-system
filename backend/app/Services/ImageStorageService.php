<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ImageStorageService
{
    public function upload(UploadedFile $file, string $directory): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $fileName = now()->format('Ymd_His').'_'.Str::random(8).'.'.$extension;

        if (! $this->usesCloudinary()) {
            $path = $file->storeAs($directory, $fileName, 'public');

            return Storage::url($path);
        }

        return $this->uploadToCloudinary($file, $directory, $fileName);
    }

    private function usesCloudinary(): bool
    {
        return filled(config('services.cloudinary.cloud_name'))
            && filled(config('services.cloudinary.api_key'))
            && filled(config('services.cloudinary.api_secret'));
    }

    private function uploadToCloudinary(UploadedFile $file, string $directory, string $fileName): string
    {
        $timestamp = now()->timestamp;
        $folder = 'mennavi/'.trim($directory, '/');
        $publicId = pathinfo($fileName, PATHINFO_FILENAME);
        $signatureParameters = [
            'folder' => $folder,
            'public_id' => $publicId,
            'timestamp' => $timestamp,
        ];
        ksort($signatureParameters);

        $signature = sha1(
            collect($signatureParameters)
                ->map(fn (string|int $value, string $key) => "{$key}={$value}")
                ->implode('&').config('services.cloudinary.api_secret')
        );

        $response = Http::timeout(30)
            ->attach('file', fopen($file->getRealPath(), 'r'), $file->getClientOriginalName())
            ->post('https://api.cloudinary.com/v1_1/'.config('services.cloudinary.cloud_name').'/image/upload', [
                ...$signatureParameters,
                'api_key' => config('services.cloudinary.api_key'),
                'signature' => $signature,
            ]);

        $response->throw();
        $imageUrl = $response->json('secure_url');

        if (! is_string($imageUrl) || $imageUrl === '') {
            throw new RuntimeException('画像ストレージから画像URLを取得できませんでした。');
        }

        return $imageUrl;
    }
}
