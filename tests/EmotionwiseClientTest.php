<?php

declare(strict_types=1);

namespace Emotionwise\Tests;

use Emotionwise\EmotionwiseClient;
use Emotionwise\Exceptions\EmotionwiseAPIError;
use Emotionwise\Exceptions\EmotionwiseAuthError;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EmotionwiseClientTest extends TestCase
{
    public function testRejectsMissingApiKey(): void
    {
        $this->expectException(EmotionwiseAuthError::class);
        new EmotionwiseClient();
    }

    public function testRejectsWhitespaceApiKey(): void
    {
        $this->expectException(EmotionwiseAuthError::class);
        new EmotionwiseClient('   ');
    }

    public function testDetectEmotionSendsExpectedPayload(): void
    {
        $client = new MockEmotionwiseClient(
            apiKey: 'test-key',
            responseStatusCode: 200,
            responseBody: '{"ok":true}',
        );

        $resp = $client->detectEmotion('hello', 'daily journal');

        self::assertSame(['ok' => true], $resp);
        self::assertSame('/api/v1/tools/emotion-detector', parse_url($client->lastUrl ?? '', PHP_URL_PATH));
        self::assertSame('test-key', $client->lastHeaders['X-API-Key'] ?? null);
        self::assertSame(
            ['message' => 'hello', 'context' => 'daily journal'],
            json_decode($client->lastBody ?? '', true)
        );
    }

    public function testSubmitFeedbackOmitsNullOptionalFields(): void
    {
        $client = new MockEmotionwiseClient(
            apiKey: 'test-key',
            responseStatusCode: 200,
            responseBody: '{"ok":true}',
        );

        $client->submitFeedback(text: 'hello', predictedEmotions: ['joy']);

        $body = json_decode($client->lastBody ?? '', true);

        self::assertSame(
            [
                'text' => 'hello',
                'predicted_emotions' => ['joy'],
                'language_code' => 'en',
            ],
            $body
        );
        self::assertArrayNotHasKey('suggested_emotions', $body);
        self::assertArrayNotHasKey('predicted_sarcasm', $body);
        self::assertArrayNotHasKey('sarcasm_feedback', $body);
        self::assertArrayNotHasKey('comment', $body);
    }

    public function testRaisesApiErrorOn4xx(): void
    {
        $client = new MockEmotionwiseClient(
            apiKey: 'bad-key',
            responseStatusCode: 401,
            responseBody: '{"detail":"Unauthorized"}',
        );

        try {
            $client->detectEmotion('hello');
            self::fail('Expected EmotionwiseAPIError was not thrown.');
        } catch (EmotionwiseAPIError $e) {
            self::assertSame(401, $e->getStatusCode());
            self::assertSame(['detail' => 'Unauthorized'], $e->getResponseBody());
        }
    }

    public function testRaisesApiErrorWithTextBody(): void
    {
        $client = new MockEmotionwiseClient(
            apiKey: 'test-key',
            responseStatusCode: 500,
            responseBody: 'Internal Server Error',
        );

        try {
            $client->detectEmotion('hello');
            self::fail('Expected EmotionwiseAPIError was not thrown.');
        } catch (EmotionwiseAPIError $e) {
            self::assertSame(500, $e->getStatusCode());
            self::assertSame('Internal Server Error', $e->getResponseBody());
        }
    }

    public function testRejectsMessageOutsideAllowedLength(): void
    {
        $client = new MockEmotionwiseClient(
            apiKey: 'test-key',
            responseStatusCode: 200,
            responseBody: '{"ok":true}',
        );

        $this->expectException(InvalidArgumentException::class);
        $client->detectEmotion('');
    }

    public function testRejectsMessageOver1000Characters(): void
    {
        $client = new MockEmotionwiseClient(
            apiKey: 'test-key',
            responseStatusCode: 200,
            responseBody: '{"ok":true}',
        );

        $this->expectException(InvalidArgumentException::class);
        $client->detectEmotion(str_repeat('a', 1001));
    }

    public function testExtraCannotOverrideReservedKeys(): void
    {
        $client = new MockEmotionwiseClient(
            apiKey: 'test-key',
            responseStatusCode: 200,
            responseBody: '{"ok":true}',
        );

        $this->expectException(InvalidArgumentException::class);
        $client->detectEmotion('hello', extra: ['message' => 'override']);
    }

    public function testRequestReturnsNullForEmptyBody(): void
    {
        $client = new MockEmotionwiseClient(
            apiKey: 'test-key',
            responseStatusCode: 204,
            responseBody: '',
        );

        self::assertNull($client->request('DELETE', '/api/v1/resource'));
    }

    public function testRequestReturnsTextForNonJson(): void
    {
        $client = new MockEmotionwiseClient(
            apiKey: 'test-key',
            responseStatusCode: 200,
            responseBody: 'plain text response',
        );

        self::assertSame('plain text response', $client->request('GET', '/api/v1/health'));
    }

    public function testCustomHeadersCannotOverrideApiKey(): void
    {
        $client = new MockEmotionwiseClient(
            apiKey: 'real-key',
            responseStatusCode: 200,
            responseBody: '{"ok":true}',
        );

        $result = $client->request('GET', '/test', headers: ['X-API-Key' => 'spoofed-key']);

        self::assertSame(['ok' => true], $result);
        self::assertSame('real-key', $client->lastHeaders['X-API-Key'] ?? null);
    }
}

class MockEmotionwiseClient extends EmotionwiseClient
{
    public ?string $lastMethod = null;
    public ?string $lastUrl = null;
    public ?string $lastBody = null;
    /** @var array<string, string> */
    public array $lastHeaders = [];

    public function __construct(
        ?string $apiKey,
        private readonly int $responseStatusCode,
        private readonly string $responseBody,
    ) {
        parent::__construct(apiKey: $apiKey, baseUrl: 'https://api.emotionwise.ai');
    }

    protected function sendHttpRequest(string $method, string $url, ?string $jsonBody, array $headers): array
    {
        $this->lastMethod = $method;
        $this->lastUrl = $url;
        $this->lastBody = $jsonBody;
        $this->lastHeaders = $headers;

        return [$this->responseStatusCode, $this->responseBody];
    }
}
