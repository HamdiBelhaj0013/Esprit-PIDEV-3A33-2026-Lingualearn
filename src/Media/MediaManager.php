<?php

namespace App\Media;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final class MediaManager
{
    public function assertAllowed(UploadedFile $file, string $type): void
    {
        $rules = $this->rules($type);

        $size = $file->getSize();
        if ($size !== null && $size > $rules['max_bytes']) {
            throw new \RuntimeException("Fichier trop grand pour $type (max {$rules['max_human']}).");
        }

        $mime = $file->getMimeType() ?? '';
        if (!in_array($mime, $rules['mimes'], true)) {
            throw new \RuntimeException("Type MIME interdit pour $type ($mime).");
        }
    }

    public function rules(string $type): array
    {
        return match ($type) {
            MediaType::LESSON_VIDEO => [
                'mimes' => ['video/mp4','video/webm'],
                'max_bytes' => 200 * 1024 * 1024,
                'max_human' => '200MB',
            ],
            MediaType::LESSON_THUMB => [
                'mimes' => ['image/jpeg','image/png','image/webp'],
                'max_bytes' => 3 * 1024 * 1024,
                'max_human' => '3MB',
            ],
            MediaType::LESSON_RESOURCE => [
                'mimes' => [
                    'application/pdf',
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                ],
                'max_bytes' => 20 * 1024 * 1024,
                'max_human' => '20MB',
            ],
            default => throw new \InvalidArgumentException("Type inconnu: $type"),
        };
    }
}