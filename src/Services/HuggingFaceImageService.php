<?php

namespace BeyondTheseWalls\FileManager\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HuggingFaceImageService
{
    private const MODEL = 'black-forest-labs/FLUX.1-schnell';
    private const API_URL = 'https://router.huggingface.co/hf-inference/models/';

    /**
     * Generate an image from a text prompt and save it to storage.
     *
     * @throws \RuntimeException
     */
    public function generate(string $prompt, string $folder = 'images', int $width = 1024, int $height = 1024): array
    {
        $key = config('services.huggingface.key');

        if (! $key) {
            throw new \RuntimeException('Hugging Face API key is not configured.');
        }

        $response = Http::timeout(60)
            ->withToken($key)
            ->withHeaders(['Accept' => 'image/jpeg'])
            ->post(self::API_URL . self::MODEL, [
                'inputs'     => $prompt,
                'parameters' => [
                    'width'  => $width,
                    'height' => $height,
                ],
            ]);

        if ($response->failed()) {
            // Model may be loading — HF returns 503 with estimated_time
            if ($response->status() === 503) {
                $wait = $response->json('estimated_time') ?? 20;
                throw new \RuntimeException("Model is loading, please try again in {$wait} seconds.");
            }

            $error = $response->json('error') ?? 'Unknown API error (HTTP ' . $response->status() . ')';
            throw new \RuntimeException('Hugging Face API error: ' . $error);
        }

        $imageData = $response->body();

        if (empty($imageData)) {
            throw new \RuntimeException('No image data returned. Try a different prompt.');
        }

        // Detect format from content-type header
        $contentType = $response->header('Content-Type') ?? 'image/jpeg';
        $extension = 'jpg';

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
