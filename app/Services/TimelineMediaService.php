<?php

declare(strict_types=1);

namespace App\Services;

class TimelineMediaService
{
    public function allowedMimeMap(): array
    {
        return [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/ogg' => 'ogv',
            'video/quicktime' => 'mov',
            'video/x-msvideo' => 'avi',
            'video/x-matroska' => 'mkv',
            'video/x-m4v' => 'm4v',
            'video/mpeg' => 'mpeg',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'text/plain' => 'txt',
        ];
    }

    public function kindFromMimeOrFilename(?string $mimeType, string $filename): string
    {
        $mime = strtolower((string)$mimeType);
        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }
        if ($mime === 'application/pdf') {
            return 'document';
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true)) {
            return 'image';
        }
        if (in_array($ext, ['mp4', 'webm', 'ogg', 'ogv', 'mov', 'avi', 'mkv', 'm4v', 'mpeg'], true)) {
            return 'video';
        }
        if (in_array($ext, ['pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx'], true)) {
            return 'document';
        }

        return 'file';
    }

    public function normalizeAttachmentToMedia(string|array|null $attachment, int $patientId): array
    {
        if ($attachment === null || $attachment === '') {
            return [];
        }

        if (is_string($attachment)) {
            $decoded = json_decode($attachment, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $this->normalizeAttachmentToMedia($decoded, $patientId);
            }

            $filename = $this->filenameFromLegacyAttachment($attachment);
            if ($filename === '') {
                return [];
            }
            return [$this->buildMediaItem($patientId, $filename)];
        }

        $media = [];
        foreach ($attachment as $row) {
            if (is_string($row)) {
                $filename = $this->filenameFromLegacyAttachment($row);
                if ($filename === '') {
                    continue;
                }
                $media[] = $this->buildMediaItem($patientId, $filename);
                continue;
            }
            if (!is_array($row)) {
                continue;
            }

            $filename = basename((string)($row['filename'] ?? $row['file'] ?? ''));
            if ($filename === '') {
                $filename = $this->filenameFromLegacyAttachment((string)($row['url'] ?? $row['relative_path'] ?? ''));
            }
            if ($filename === '') {
                continue;
            }

            $media[] = $this->buildMediaItem(
                $patientId,
                $filename,
                isset($row['mime_type']) ? (string)$row['mime_type'] : null,
                isset($row['size']) ? (int)$row['size'] : null,
                isset($row['original_name']) ? (string)$row['original_name'] : null
            );
        }

        return $media;
    }

    public function buildMediaItem(
        int $patientId,
        string $filename,
        ?string $mimeType = null,
        ?int $size = null,
        ?string $originalName = null
    ): array {
        $safeFilename = basename($filename);
        $relativePath = 'patients/' . $patientId . '/timeline/' . $safeFilename;
        $mobileUrl = '/api/mobile/patients/' . $patientId . '/media/' . rawurlencode($safeFilename);
        $webUrl = '/patienten/' . $patientId . '/dokumente/' . rawurlencode($safeFilename);
        $kind = $this->kindFromMimeOrFilename($mimeType, $safeFilename);

        return [
            'id' => null,
            'filename' => $safeFilename,
            'relative_path' => $relativePath,
            'mime_type' => $mimeType,
            'kind' => $kind,
            'url' => $mobileUrl,
            'web_url' => $webUrl,
            'thumbnail_url' => $kind === 'image' ? $mobileUrl : null,
            'size' => $size,
            'original_name' => $originalName,
        ];
    }

    public function enrichTimelineEntry(array $entry): array
    {
        $patientId = (int)($entry['patient_id'] ?? 0);
        $media = $this->normalizeAttachmentToMedia($entry['attachment'] ?? null, $patientId);
        $entry['media'] = $media;
        $entry['author'] = $entry['user_name'] ?? null;
        $entry['entry_type'] = $entry['type'] ?? 'note';
        $entry['created_at'] = $entry['created_at'] ?? ($entry['entry_date'] ?? null);
        if (!empty($media[0]['url'])) {
            $entry['file_url'] = $media[0]['url'];
        }
        return $entry;
    }

    public function normalizeTimeline(array $timeline): array
    {
        return array_map(fn(array $entry): array => $this->enrichTimelineEntry($entry), $timeline);
    }

    public function filenameFromLegacyAttachment(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '';
        }

        if (str_contains($raw, '://')) {
            $path = (string)parse_url($raw, PHP_URL_PATH);
            return basename(urldecode($path));
        }

        if (str_starts_with($raw, '/')) {
            return basename(urldecode($raw));
        }

        return basename($raw);
    }
}
