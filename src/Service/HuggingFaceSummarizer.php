<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HuggingFaceSummarizer
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $hfApiKey,
        private readonly string $hfModel,
    ) {}

    public function summarize(string $text, int $maxLength = 130, int $minLength = 30): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        // évite payload trop grand
        $text = mb_substr($text, 0, 8000);

        // ✅ NOUVEL endpoint Hugging Face
        $url = sprintf('https://router.huggingface.co/hf-inference/models/%s', $this->hfModel);

        $response = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->hfApiKey,
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'inputs' => $text,
                'parameters' => [
                    'max_length' => $maxLength,
                    'min_length' => $minLength,
                    'do_sample'  => false,
                ],
            ],
        ]);

        $status = $response->getStatusCode();
        $data = $response->toArray(false);

        if ($status >= 400) {
            $msg = is_array($data) && isset($data['error']) ? $data['error'] : $response->getContent(false);
            throw new \RuntimeException('HF error: ' . $msg);
        }

        // format habituel: [ { summary_text: "..." } ]
        if (is_array($data) && isset($data[0]['summary_text'])) {
            return (string) $data[0]['summary_text'];
        }

        // fallback
        if (is_array($data) && isset($data['summary_text'])) {
            return (string) $data['summary_text'];
        }

        throw new \RuntimeException('HF response unexpected: ' . json_encode($data));
    }
}