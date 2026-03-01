<?php

declare(strict_types=1);

namespace Emotionwise;

use Emotionwise\Exceptions\EmotionwiseAPIError;
use Emotionwise\Exceptions\EmotionwiseAuthError;
use InvalidArgumentException;

class EmotionwiseClient
{
    private string $baseUrl;

    public function __construct(
        private readonly ?string $apiKey = null,
        string $baseUrl = 'https://api.emotionwise.ai',
        private readonly int $timeout = 15,
    ) {
        if ($this->apiKey === null || trim($this->apiKey) === '') {
            throw new EmotionwiseAuthError('apiKey is required.');
        }

        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * @param array<string, mixed>|null $params
     * @param array<string, mixed>|null $json
     * @param array<string, string>|null $headers
     */
    public function request(
        string $method,
        string $path,
        ?array $params = null,
        ?array $json = null,
        ?array $headers = null,
    ): mixed {
        $path = str_starts_with($path, '/') ? $path : '/' . $path;
        $url = $this->baseUrl . $path;

        if ($params !== null && $params !== []) {
            $url .= '?' . http_build_query($params);
        }

        $encodedBody = $json !== null ? json_encode($json, JSON_THROW_ON_ERROR) : null;

        [$statusCode, $responseBody] = $this->sendHttpRequest(
            strtoupper($method),
            $url,
            $encodedBody,
            $this->buildHeaders($headers)
        );

        if ($statusCode >= 400) {
            throw new EmotionwiseAPIError(
                sprintf('Emotionwise API error (%d)', $statusCode),
                $statusCode,
                $this->tryDecodeJson($responseBody) ?? $responseBody,
            );
        }

        if ($responseBody === '' || $responseBody === null) {
            return null;
        }

        return $this->tryDecodeJson($responseBody) ?? $responseBody;
    }

    /**
     * @param array<string, mixed>|null $extra
     */
    public function detectEmotion(
        string $message,
        ?string $context = null,
        string $endpoint = '/api/v1/tools/emotion-detector',
        ?array $extra = null,
    ): mixed {
        $length = mb_strlen($message);
        if ($length < 1 || $length > 1000) {
            throw new InvalidArgumentException('message length must be between 1 and 1000 characters.');
        }

        $payload = ['message' => $message];
        if ($context !== null) {
            $payload['context'] = $context;
        }

        if ($extra !== null) {
            $reserved = ['message', 'context'];
            $conflicts = array_intersect($reserved, array_keys($extra));
            if ($conflicts !== []) {
                sort($conflicts);
                throw new InvalidArgumentException(
                    sprintf('extra must not override reserved keys: [%s]', implode(', ', $conflicts))
                );
            }
            $payload = array_merge($payload, $extra);
        }

        return $this->request('POST', $endpoint, json: $payload);
    }

    /**
     * @param list<string> $predictedEmotions
     * @param list<string>|null $suggestedEmotions
     */
    public function submitFeedback(
        string $text,
        array $predictedEmotions,
        ?array $suggestedEmotions = null,
        ?bool $predictedSarcasm = null,
        ?bool $sarcasmFeedback = null,
        ?string $comment = null,
        string $languageCode = 'en',
        string $endpoint = '/api/v1/feedback/submit',
    ): mixed {
        $payload = [
            'text' => $text,
            'predicted_emotions' => $predictedEmotions,
            'language_code' => $languageCode,
        ];

        if ($suggestedEmotions !== null) {
            $payload['suggested_emotions'] = $suggestedEmotions;
        }
        if ($predictedSarcasm !== null) {
            $payload['predicted_sarcasm'] = $predictedSarcasm;
        }
        if ($sarcasmFeedback !== null) {
            $payload['sarcasm_feedback'] = $sarcasmFeedback;
        }
        if ($comment !== null) {
            $payload['comment'] = $comment;
        }

        return $this->request('POST', $endpoint, json: $payload);
    }

    public function close(): void
    {
    }

    /**
     * @param array<string, string>|null $headers
     * @return array<string, string>
     */
    private function buildHeaders(?array $headers = null): array
    {
        $finalHeaders = $headers ?? [];
        $finalHeaders['Accept'] = 'application/json';
        $finalHeaders['X-API-Key'] = $this->apiKey;

        return $finalHeaders;
    }

    /**
     * @param array<string, string> $headers
     * @return array{0: int, 1: string}
     */
    protected function sendHttpRequest(string $method, string $url, ?string $jsonBody, array $headers): array
    {
        $ch = curl_init($url);

        if ($ch === false) {
            throw new EmotionwiseAPIError('Failed to initialize cURL.', 0);
        }

        $formattedHeaders = [];
        foreach ($headers as $name => $value) {
            $formattedHeaders[] = sprintf('%s: %s', $name, $value);
        }
        if ($jsonBody !== null) {
            $formattedHeaders[] = 'Content-Type: application/json';
        }

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $formattedHeaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        if ($jsonBody !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
        }

        $responseBody = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            throw new EmotionwiseAPIError(
                sprintf('HTTP request failed: %s', $curlError !== '' ? $curlError : 'unknown error'),
                0,
            );
        }

        return [$statusCode, $responseBody];
    }

    private function tryDecodeJson(string $body): mixed
    {
        try {
            return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }
}
