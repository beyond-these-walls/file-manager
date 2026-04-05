<?php

namespace BeyondTheseWalls\FileManager\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CloudflareImageService
{
    private const MODEL = '@cf/black-forest-labs/flux-1-schnell';

    /**
     * Generate an image from a text prompt and save it to storage.
     *
     * @throws \RuntimeException
     */
    public function generate(string $prompt, string $folder = 'images', int $width = 1024, int $height = 1024): array
    {
        $accountId = config('services.cloudflare.account_id');
        $token     = config('services.cloudflare.api_token');

        if (! $accountId || ! $token) {
            throw new \RuntimeException('Cloudflare account ID or API token is not configured.');
        }

        $url = "https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai/run/" . self::MODEL;

        $response = Http::timeout(60)
            ->withToken($token)
            ->post($url, [
                'prompt'    => $prompt,
                'num_steps' => 4,
                'width'     => $width,
                'height'    => $height,
            ]);

        if ($response->failed()) {
            $error = $response->json('errors.0.message')
                ?? $response->json('error')
                ?? 'Unknown API error (HTTP ' . $response->status() . ')';
            throw new \RuntimeException('Cloudflare AI error: ' . $error);
        }

        // Response may be raw binary or JSON-wrapped base64
        $contentType = $response->header('Content-Type') ?? '';

        if (str_contains($contentType, 'image/')) {
            $imageData = $response->body();
            $extension = str_contains($contentType, 'jpeg') ? 'jpg' : 'png';
        } else {
            // JSON response with base64 image
            $b64 = $response->json('result.image') ?? $response->json('image');
            if (! $b64) {
                throw new \RuntimeException('No image data returned. Try a different prompt.');
            }
            $imageData = base64_decode($b64);
            $extension = 'png';
        }

        if (empty($imageData)) {
            throw new \RuntimeException('No image data returned. Try a different prompt.');
        }

        $filename    = 'ai_' . now()->format('YmdHis') . '_' . Str::random(6) . '.' . $extension;
        $storagePath = 'uploads/' . trim($folder, '/') . '/' . $filename;

        Storage::disk('public')->put($storagePath, $imageData);

        return [
            'name'     => $filename,
            'path'     => $storagePath,
            'url'      => Storage::disk('public')->url($storagePath),
            'size'     => strlen($imageData),
            'type'     => 'image',
            'modified' => time(),
        ];
    }
}
