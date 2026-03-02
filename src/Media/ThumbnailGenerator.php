<?php

namespace App\Media;

use Symfony\Component\Process\Process;

final class ThumbnailGenerator
{
    public function generateFromVideo(string $videoAbsolutePath, string $outputAbsolutePath): void
    {
        $process = new Process([
            'ffmpeg',
            '-y',
            '-ss', '00:00:02',
            '-i', $videoAbsolutePath,
            '-frames:v', '1',
            '-q:v', '2',
            $outputAbsolutePath,
        ]);

        $process->setTimeout(60);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('FFmpeg error: ' . $process->getErrorOutput());
        }
    }
}