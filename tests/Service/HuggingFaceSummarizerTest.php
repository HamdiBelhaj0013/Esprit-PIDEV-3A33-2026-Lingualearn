<?php

namespace tests\Service;

use App\Service\HuggingFaceSummarizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HuggingFaceSummarizerTest extends TestCase
{
    private function getHeaderValue(array $headers, string $wantedName): ?string
    {
        $wanted = strtolower($wantedName);

        // Cas tableau associatif
        foreach ($headers as $k => $v) {
            if (is_string($k) && strtolower($k) === $wanted) {
                if (is_array($v)) {
                    return (string) ($v[0] ?? null);
                }
                return is_string($v) ? $v : null;
            }
        }

        // Cas liste de strings
        foreach ($headers as $h) {
            if (!is_string($h)) {
                continue;
            }

            $pos = strpos($h, ':');
            if ($pos === false) {
                continue;
            }

            $name = strtolower(trim(substr($h, 0, $pos)));
            $value = trim(substr($h, $pos + 1));

            if ($name === $wanted) {
                return $value;
            }
        }

        return null;
    }

    public function testSummarizeReturnsEmptyStringWhenTextIsEmpty(): void
    {
        $client = new MockHttpClient(function () {
            self::fail('Aucun appel HTTP ne doit être fait quand le texte est vide.');
        });

        $service = new HuggingFaceSummarizer(
            $client,
            'fake_api_key',
            'facebook/bart-large-cnn'
        );

        self::assertSame('', $service->summarize('   '));
    }

    public function testSummarizeSuccessReturnsSummaryText(): void
    {
        $mockBody = json_encode([
            ['summary_text' => 'This is a short summary.']
        ], JSON_THROW_ON_ERROR);

        $response = new MockResponse($mockBody, [
            'http_code' => 200,
            'response_headers' => ['content-type: application/json'],
        ]);

        $client = new MockHttpClient(function (string $method, string $url, array $options) use ($response) {

            self::assertSame('POST', $method);

            self::assertStringContainsString(
                'router.huggingface.co',
                $url
            );

            self::assertArrayHasKey('headers', $options);

            $headers = $options['headers'] ?? [];

            $auth = $this->getHeaderValue($headers, 'Authorization');
            $ct   = $this->getHeaderValue($headers, 'Content-Type');

            self::assertSame('Bearer fake_api_key', $auth);
            self::assertSame('application/json', $ct);

            // Symfony peut transformer json → body
            $payload = null;

            if (array_key_exists('json', $options)) {
                $payload = $options['json'];
            }
            elseif (array_key_exists('body', $options)) {
                $payload = json_decode($options['body'], true);
                self::assertIsArray($payload);
            }
            else {
                self::fail('Ni json ni body trouvé dans la requête.');
            }

            self::assertArrayHasKey('inputs', $payload);
            self::assertArrayHasKey('parameters', $payload);

            self::assertSame(130, $payload['parameters']['max_length']);
            self::assertSame(30, $payload['parameters']['min_length']);
            self::assertFalse($payload['parameters']['do_sample']);

            return $response;
        });

        $service = new HuggingFaceSummarizer(
            $client,
            'fake_api_key',
            'facebook/bart-large-cnn'
        );

        $result = $service->summarize('Hello world');

        self::assertSame('This is a short summary.', $result);
    }

    public function testSummarizeThrowsRuntimeExceptionOnHttpError(): void
    {
        $mockBody = json_encode([
            'error' => 'Model is loading'
        ], JSON_THROW_ON_ERROR);

        $response = new MockResponse($mockBody, [
            'http_code' => 503,
            'response_headers' => ['content-type: application/json'],
        ]);

        $client = new MockHttpClient($response);

        $service = new HuggingFaceSummarizer(
            $client,
            'fake_api_key',
            'facebook/bart-large-cnn'
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HF error');

        $service->summarize('Some text');
    }

    public function testSummarizeThrowsRuntimeExceptionOnUnexpectedResponseFormat(): void
    {
        $mockBody = json_encode([
            'foo' => 'bar'
        ], JSON_THROW_ON_ERROR);

        $response = new MockResponse($mockBody, [
            'http_code' => 200,
            'response_headers' => ['content-type: application/json'],
        ]);

        $client = new MockHttpClient($response);

        $service = new HuggingFaceSummarizer(
            $client,
            'fake_api_key',
            'facebook/bart-large-cnn'
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HF response unexpected');

        $service->summarize('Some text');
    }
}
