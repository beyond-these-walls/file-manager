<?php

namespace BeyondTheseWalls\FileManager\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PicsartImageService
{
    private const API_URL    = 'https://genai-api.picsart.io/v1/text2image';
    private const RESULT_URL = 'https://genai-api.picsart.io/v1/text2image/inferences/';

    /**
     * Generate an image from a text prompt and save it to storage.
     *
     * @throws \RuntimeException
     */
    public function generate(string $prompt, string $folder = 'images', int $width = 1024, int $height = 1024): array
    {
        $key = config('services.picsart.key');

        if (! $key) {
            throw new \RuntimeException('Picsart API key is not configured.');
        }

        // Submit the generation job
        $response = Http::timeout(30)
            ->withHeaders([
                'X-Picsart-API-Key' => $key,
                'Accept'            => 'application/json',
            ])
            ->post(self::API_URL, [
                'prompt' => $prompt,
                'width'  => $width,
                'height' => $height,
                'count'  => 1,
            ]);

        if ($response->failed()) {
            $message = $response->json('message') ?? $response->json('error') ?? 'Unknown API error (HTTP ' . $response->status() . ')';
            $detail  = $response->json('detail');
            $error   = $detail ? $message . ': ' . $detail : $message;
            throw new \RuntimeException($error);
        }

        $body        = $response->json();
        $inferenceId = $body['inference_id'] ?? null;

        // Synchronous FINISHED response
        $imageUrl = $this->extractImageUrl($body);

        // Async ACCEPTED response — poll for result
        if (! $imageUrl && $inferenceId) {
            $imageUrl = $this->pollForResult($inferenceId, $key);
        }

        if (! $imageUrl) {
            throw new \RuntimeException('Picsart did not return an image URL. Response: ' . json_encode($body));
        }

        // Download the generated image
        $imageResponse = Http::timeout(60)->get($imageUrl);

        if ($imageResponse->failed()) {
            throw new \RuntimeException('Failed to download the generated image from Picsart.');
        }

        $imageData = $imageResponse->body();

        if (empty($imageData)) {
            throw new \RuntimeException('Downloaded image data is empty.');
        }

        $filename    = 'ai_' . now()->format('YmdHis') . '_' . Str::random(6) . '.jpg';
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

    /**
     * Extract image URL from a FINISHED response body.
     */
    private function extractImageUrl(array $body): ?string
    {
        return $body['data'][0]['url'] ?? $body['data']['url'] ?? null;
    }

    /**
     * Poll GET /v1/text2image/inferences/{id} until status == FINISHED.
     *
     * @throws \RuntimeException
     */
    private function pollForResult(string $inferenceId, string $key): string
    {
        $maxAttempts  = 20;
        $sleepSeconds = 3;

        for ($i = 0; $i < $maxAttempts; $i++) {
            sleep($sleepSeconds);

            $response = Http::timeout(15)
                ->withHeaders([
                    'X-Picsart-API-Key' => $key,
                    'Accept'            => 'application/json',
                ])
                ->get(self::RESULT_URL . $inferenceId);

            if ($response->failed()) {
                throw new \RuntimeException(
                    'Picsart polling error (HTTP ' . $response->status() . '): ' . $response->body()
                );
            }

            $body   = $response->json();
            $status = $body['status'] ?? 'UNKNOWN';

            if ($status === 'FINISHED' || $status === 'done' || $status === 'success' || $status === 'completed') {
                $url = $this->extractImageUrl($body);
                if (! $url) {
                    throw new \RuntimeException('Picsart returned FINISHED but no image URL. Response: ' . json_encode($body));
                }
                return $url;
            }

            if ($status === 'FAILED' || $status === 'failed' || $status === 'error') {
                $msg = $body['message'] ?? $body['error'] ?? 'Generation failed';
                throw new \RuntimeException('Picsart generation failed: ' . $msg);
            }

            // status === 'PROCESSING' or 'ACCEPTED' — keep polling
        }

        throw new \RuntimeException('Picsart image generation timed out after ' . ($maxAttempts * $sleepSeconds) . ' seconds.');
    }
}
