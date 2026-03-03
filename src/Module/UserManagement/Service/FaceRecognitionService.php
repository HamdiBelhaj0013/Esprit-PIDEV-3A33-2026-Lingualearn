<?php

namespace App\Module\UserManagement\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Thin wrapper around the Python face-recognition microservice.
 * Base URL is configured via FACE_API_URL env var (default: http://localhost:5001).
 */
class FaceRecognitionService
{
    private string $baseUrl;

    public function __construct(
        private HttpClientInterface $httpClient,
        string $faceApiUrl = 'http://localhost:5001',
    ) {
        $this->baseUrl = rtrim($faceApiUrl, '/');
    }

    /**
     * Enroll (register) an admin's face.
     *
     * @param int    $userId   Admin's DB user ID
     * @param string $imageB64 Base64-encoded image (data URI or raw)
     *
     * @return array{status: string}
     * @throws \RuntimeException on API error or no face detected
     */
    public function enroll(int $userId, string $imageB64): array
    {
        $response = $this->post('/enroll', [
            'user_id' => $userId,
            'image'   => $imageB64,
        ]);

        if (isset($response['error'])) {
            throw new \RuntimeException('Face enrollment failed: ' . $response['error']);
        }

        return $response;
    }

    /**
     * Verify a live frame against a stored embedding.
     *
     * @param int    $userId   Admin's DB user ID
     * @param string $imageB64 Base64-encoded webcam frame
     *
     * @return array{match: bool, distance: float}
     * @throws \RuntimeException on transport / API error
     */
    public function verify(int $userId, string $imageB64): array
    {
        $response = $this->post('/verify', [
            'user_id' => $userId,
            'image'   => $imageB64,
        ]);

        if (isset($response['error'])) {
            throw new \RuntimeException('Face verification failed: ' . $response['error']);
        }

        return $response;
    }

    /**
     * Delete a stored embedding (call when removing an admin account).
     */
    public function deleteEnrollment(int $userId): void
    {
        try {
            $this->httpClient->request('DELETE', $this->baseUrl . '/delete/' . $userId);
        } catch (TransportExceptionInterface) {
            // Non-critical — log and continue
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function post(string $path, array $body): array
    {
        try {
            $response = $this->httpClient->request('POST', $this->baseUrl . $path, [
                'json'    => $body,
                'timeout' => 10,
            ]);

            return $response->toArray(throw: false);
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('Face API unreachable: ' . $e->getMessage());
        }
    }
}
