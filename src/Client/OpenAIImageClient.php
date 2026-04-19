<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitImages\Client;

use CarmeloSantana\CoquiToolkitImages\Contract\ImageClientInterface;
use CarmeloSantana\CoquiToolkitImages\Contract\ImageGenerationRequest;
use CarmeloSantana\CoquiToolkitImages\Contract\ImageGenerationResult;
use CarmeloSantana\CoquiToolkitImages\Exception\ImageToolkitException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OpenAIImageClient implements ImageClientInterface
{
    private const string DEFAULT_BASE_URL = 'https://api.openai.com/v1';
    private const int DEFAULT_TIMEOUT = 240;

    private HttpClientInterface $httpClient;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = self::DEFAULT_BASE_URL,
        ?HttpClientInterface $httpClient = null,
        int $timeout = self::DEFAULT_TIMEOUT,
    ) {
        $this->httpClient = $httpClient ?? HttpClient::create(['timeout' => $timeout]);
    }

    /**
     * @param array<string, mixed> $settings
     */
    public static function fromSettings(array $settings): self
    {
        $apiKey = is_string($settings['apiKey'] ?? null) && $settings['apiKey'] !== ''
            ? $settings['apiKey']
            : (string) (getenv('OPENAI_API_KEY') ?: '');

        $baseUrl = is_string($settings['baseUrl'] ?? null) && $settings['baseUrl'] !== ''
            ? rtrim($settings['baseUrl'], '/')
            : self::DEFAULT_BASE_URL;

        $timeout = is_int($settings['timeout'] ?? null) && $settings['timeout'] > 0
            ? $settings['timeout']
            : self::DEFAULT_TIMEOUT;

        return new self($apiKey, $baseUrl, timeout: $timeout);
    }

    public function hasCredentials(): bool
    {
        return $this->apiKey !== '';
    }

    public function generate(ImageGenerationRequest $request, string $targetPath): ImageGenerationResult
    {
        if ($this->apiKey === '') {
            throw ImageToolkitException::openAiCredentialsMissing();
        }

        $quality = $this->normalizeQuality($request->quality);

        $payload = [
            'model' => $request->model,
            'prompt' => $request->prompt,
            'size' => $request->size ?? '1024x1024',
            'quality' => $quality,
            'response_format' => 'b64_json',
        ];

        try {
            $response = $this->httpClient->request('POST', $this->baseUrl . '/images/generations', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            /** @var array<string, mixed> $decoded */
            $decoded = $response->toArray(false);
        } catch (HttpExceptionInterface $e) {
            throw ImageToolkitException::providerFailure($e->getResponse()->getContent(false));
        } catch (\Throwable $e) {
            throw ImageToolkitException::providerFailure($e->getMessage());
        }

        $encoded = $decoded['data'][0]['b64_json'] ?? null;
        if (!is_string($encoded) || $encoded === '') {
            throw ImageToolkitException::providerFailure('OpenAI image response did not include image bytes.');
        }

        $binary = base64_decode($encoded, true);
        if ($binary === false) {
            throw ImageToolkitException::providerFailure('Failed to decode OpenAI image bytes.');
        }

        $dir = dirname($targetPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($targetPath, $binary);

        return new ImageGenerationResult(
            vendor: 'openai',
            model: $request->model,
            filePath: $targetPath,
            providerPayload: [
                'revised_prompt' => $decoded['data'][0]['revised_prompt'] ?? null,
                'size' => $payload['size'],
                'quality' => $payload['quality'],
            ],
        );
    }

    private function normalizeQuality(?string $quality): string
    {
        return match (strtolower(trim((string) $quality))) {
            'hd', 'high' => 'hd',
            default => 'standard',
        };
    }
}