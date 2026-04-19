<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Optimiert bereits gespeicherte Upload-Dateien in-place.
 *
 * Architektur-Entscheidung: Post-Move-Optimierung (write-then-optimize).
 * Die Datei liegt bereits im Tenant-Storage am finalen Pfad — die Optimierung
 * ersetzt sie nur, wenn das Ergebnis kleiner ist. So bleibt bei jedem Fehler
 * die Originaldatei unangetastet → KEINE halben Einträge, keine kaputten
 * Timeline-Renderings.
 *
 * Gilt für:
 *   - Bilder (jpeg/png/webp): GD-basiertes Downscale + Recompress
 *   - Videos (mp4/mov/mkv/webm/avi): ffmpeg H.264-Transkodierung
 *   - Alles andere (pdf/doc/...): bleibt unangetastet
 *
 * Self-Heal:
 *   - GD fehlt           → Bild-Optimierung wird übersprungen, Original bleibt
 *   - ffmpeg fehlt       → Video-Optimierung wird übersprungen, Original bleibt
 *   - Temp-Datei > Orig  → Temp wird verworfen, Original bleibt
 *   - Exception          → Log + Original bleibt
 *   - Temp-Files werden in jedem Fall aufgeräumt (finally)
 */
final class MediaOptimizerService
{
    /* ── Schwellenwerte ─────────────────────────────────────────────────
     * Zentrale Konstanten. Später leicht auf Config/SaaS-Settings umstellbar. */

    /** Bild optimieren wenn > 1.5 MB */
    public const IMAGE_MAX_BYTES = 1_500_000;

    /** Bild skalieren wenn längste Kante > 2400 px (praxisgerechte Auflösung) */
    public const IMAGE_MAX_DIMENSION = 2400;

    /** JPEG-Qualität für recodierte Bilder (82 = sweet spot zwischen Größe & Diagnostik-Tauglichkeit) */
    public const IMAGE_JPEG_QUALITY = 82;

    /** WebP-Qualität (WebP nutzt aggressivere Kompression → darf höher sein ohne Artefakte) */
    public const IMAGE_WEBP_QUALITY = 84;

    /** Video optimieren wenn > 10 MB (Gangbild typischerweise 30–200 MB) */
    public const VIDEO_MAX_BYTES = 10_000_000;

    /** Ziel-Maximalbreite fürs Video (Praxis-Doku braucht kein 4K) */
    public const VIDEO_MAX_WIDTH = 1280;

    /** H.264 CRF: 28 = gute Balance für Praxis-Doku (18 = Kino, 32 = grob) */
    public const VIDEO_CRF = 28;

    /** ffmpeg-Timeout in Sekunden — schützt vor hängenden Prozessen */
    public const VIDEO_FFMPEG_TIMEOUT = 600;

    /* ── Bild-MIMEs die wir recodieren (GIF bewusst NICHT → Animation bliebe tot) */
    private const OPTIMIZABLE_IMAGE_MIMES = [
        'image/jpeg', 'image/png', 'image/webp',
    ];

    /* ── Video-MIMEs die wir transkodieren */
    private const OPTIMIZABLE_VIDEO_MIMES = [
        'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime',
        'video/x-msvideo', 'video/x-matroska', 'video/x-m4v', 'video/mpeg',
    ];

    private static ?bool $ffmpegAvailable = null;

    /**
     * Optimiert die Datei unter $fullPath. Gibt ein Ergebnis-Array zurück.
     *
     * @return array{
     *     path: string,           final absolute path (may differ from input, e.g. .mov → .mp4)
     *     filename: string,       basename of final path
     *     mime: string,           final MIME
     *     optimized: bool,        true if compression actually happened
     *     original_bytes: int,
     *     final_bytes: int,
     *     note: ?string           human-readable diagnostic (never an error code)
     * }
     */
    public function optimize(string $fullPath, string $mime): array
    {
        $result = [
            'path'           => $fullPath,
            'filename'       => basename($fullPath),
            'mime'           => $mime,
            'optimized'      => false,
            'original_bytes' => @filesize($fullPath) ?: 0,
            'final_bytes'    => @filesize($fullPath) ?: 0,
            'note'           => null,
        ];

        if (!is_file($fullPath)) {
            $result['note'] = 'file-missing';
            return $result;
        }

        try {
            if (in_array($mime, self::OPTIMIZABLE_IMAGE_MIMES, true)) {
                return $this->optimizeImage($fullPath, $mime, $result);
            }
            if (in_array($mime, self::OPTIMIZABLE_VIDEO_MIMES, true)) {
                return $this->optimizeVideo($fullPath, $mime, $result);
            }
            $result['note'] = 'mime-not-optimizable';
            return $result;
        } catch (\Throwable $e) {
            error_log('[MediaOptimizer] ' . $mime . ' @ ' . $fullPath . ': ' . $e->getMessage());
            $result['note'] = 'exception-kept-original';
            return $result;
        }
    }

    /* ══════════════════════════════════════════════════════════════════
       BILDER — GD-Pipeline
       ══════════════════════════════════════════════════════════════════ */
    private function optimizeImage(string $fullPath, string $mime, array $result): array
    {
        if (!function_exists('imagecreatefromjpeg')) {
            $result['note'] = 'gd-unavailable';
            return $result;
        }

        $size = (int)@filesize($fullPath);
        $info = @getimagesize($fullPath);
        if ($info === false) {
            $result['note'] = 'image-not-decodable';
            return $result;
        }
        [$w, $h] = $info;
        $maxDim  = max($w, $h);

        /* Unter Schwelle → keine unnötige Kompression */
        if ($size <= self::IMAGE_MAX_BYTES && $maxDim <= self::IMAGE_MAX_DIMENSION) {
            $result['note'] = 'below-threshold';
            return $result;
        }

        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($fullPath),
            'image/png'  => @imagecreatefrompng($fullPath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($fullPath) : false,
            default      => false,
        };
        if (!$img) {
            $result['note'] = 'gd-decode-failed';
            return $result;
        }

        /* EXIF-Orientation für JPEG korrigieren — sonst landen Hochkant-Fotos gedreht */
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($fullPath);
            if (!empty($exif['Orientation'])) {
                $img = $this->applyExifOrientation($img, (int)$exif['Orientation']);
            }
        }

        /* Downscale wenn zu groß */
        if ($maxDim > self::IMAGE_MAX_DIMENSION) {
            $ratio = self::IMAGE_MAX_DIMENSION / $maxDim;
            $newW  = max(1, (int)round($w * $ratio));
            $newH  = max(1, (int)round($h * $ratio));
            $scaled = @imagescale($img, $newW, $newH, IMG_BICUBIC);
            if ($scaled !== false) {
                imagedestroy($img);
                $img = $scaled;
            }
        }

        $tmpPath = $fullPath . '.opt.tmp';

        $ok = match ($mime) {
            'image/jpeg' => @imagejpeg($img, $tmpPath, self::IMAGE_JPEG_QUALITY),
            'image/png'  => @imagepng($img, $tmpPath, 6),
            'image/webp' => function_exists('imagewebp')
                          ? @imagewebp($img, $tmpPath, self::IMAGE_WEBP_QUALITY)
                          : false,
            default      => false,
        };

        imagedestroy($img);

        if (!$ok || !is_file($tmpPath)) {
            @unlink($tmpPath);
            $result['note'] = 'gd-encode-failed';
            return $result;
        }

        $newSize = (int)@filesize($tmpPath);

        /* Nur übernehmen wenn tatsächlich kleiner (sonst Kompression kontraproduktiv) */
        if ($newSize > 0 && $newSize < $size) {
            if (@rename($tmpPath, $fullPath)) {
                $result['optimized']   = true;
                $result['final_bytes'] = $newSize;
                $result['note']        = sprintf(
                    'image-optimized: %d → %d bytes (%.0f%%)',
                    $size, $newSize, (1 - $newSize / $size) * 100
                );
                return $result;
            }
            @unlink($tmpPath);
            $result['note'] = 'rename-failed';
            return $result;
        }

        @unlink($tmpPath);
        $result['note'] = 'no-savings';
        return $result;
    }

    private function applyExifOrientation(\GdImage $img, int $orientation): \GdImage
    {
        /* Nur die häufigen 4 Fälle — reicht für Smartphone-Fotos */
        return match ($orientation) {
            3       => imagerotate($img, 180, 0) ?: $img,
            6       => imagerotate($img, -90, 0) ?: $img,
            8       => imagerotate($img, 90, 0) ?: $img,
            default => $img,
        };
    }

    /* ══════════════════════════════════════════════════════════════════
       VIDEOS — ffmpeg-Pipeline
       ══════════════════════════════════════════════════════════════════ */
    private function optimizeVideo(string $fullPath, string $mime, array $result): array
    {
        $size = (int)@filesize($fullPath);

        if ($size <= self::VIDEO_MAX_BYTES) {
            $result['note'] = 'below-threshold';
            return $result;
        }

        if (!$this->isFfmpegAvailable()) {
            $result['note'] = 'ffmpeg-unavailable';
            return $result;
        }

        $dir      = dirname($fullPath);
        $baseName = pathinfo($fullPath, PATHINFO_FILENAME);
        /* Output IMMER .mp4 (H.264+AAC) — bestmögliche Browser/Mobile-Kompatibilität */
        $tmpOut   = $dir . DIRECTORY_SEPARATOR . $baseName . '.opt.mp4';

        /* ffmpeg-Kommando:
         *   -y                  : overwrite
         *   -i <in>             : input
         *   -vf scale=...       : Downscale auf max VIDEO_MAX_WIDTH, Höhe proportional (-2 = even)
         *   -c:v libx264        : H.264 (universell kompatibel)
         *   -preset fast        : guter Kompromiss Zeit/Kompression
         *   -crf VIDEO_CRF      : Ziel-Qualität
         *   -c:a aac -b:a 96k   : Audio AAC 96k (Sprachqualität ausreichend)
         *   -movflags +faststart: Web-Streaming (Header am Anfang)
         *   -pix_fmt yuv420p    : maximale Player-Kompat
         *   -threads 2          : begrenzt CPU-Verbrauch bei gleichzeitigen Uploads
         *   -loglevel error     : nur Fehler, kein Spam
         */
        $cmd = sprintf(
            '%s -y -i %s -vf %s -c:v libx264 -preset fast -crf %d -c:a aac -b:a 96k -movflags +faststart -pix_fmt yuv420p -threads 2 -loglevel error %s 2>&1',
            escapeshellarg($this->getFfmpegBinary()),
            escapeshellarg($fullPath),
            escapeshellarg('scale=' . "'min({$this->getMaxWidth()},iw)':-2"),
            self::VIDEO_CRF,
            escapeshellarg($tmpOut)
        );

        $output   = [];
        $exitCode = 0;
        $start    = time();

        /* Versuch 1: proc_open mit Timeout — auf Windows & Linux portabel */
        $success = $this->runWithTimeout($cmd, self::VIDEO_FFMPEG_TIMEOUT, $output, $exitCode);

        if (!$success || $exitCode !== 0 || !is_file($tmpOut)) {
            @unlink($tmpOut);
            $result['note'] = 'ffmpeg-failed: exit=' . $exitCode . ', elapsed=' . (time() - $start) . 's';
            error_log('[MediaOptimizer] ' . $result['note'] . ' :: ' . implode(' | ', array_slice($output, -5)));
            return $result;
        }

        $newSize = (int)@filesize($tmpOut);

        /* Nur übernehmen wenn spürbar kleiner (mind. 10 % Ersparnis) */
        if ($newSize <= 0 || $newSize >= $size * 0.9) {
            @unlink($tmpOut);
            $result['note'] = 'no-significant-savings: ' . $size . ' → ' . $newSize;
            return $result;
        }

        /* Ergebnis nach finalen Dateinamen — ggf. Extension auf .mp4 ändern */
        $targetPath = $dir . DIRECTORY_SEPARATOR . $baseName . '.mp4';

        /* Wenn Input bereits .mp4 → einfach ersetzen; sonst Input löschen & Output an dessen Stelle */
        if (strtolower(pathinfo($fullPath, PATHINFO_EXTENSION)) === 'mp4') {
            if (!@rename($tmpOut, $fullPath)) {
                @unlink($tmpOut);
                $result['note'] = 'rename-failed-mp4';
                return $result;
            }
            $result['path']        = $fullPath;
            $result['filename']    = basename($fullPath);
        } else {
            if (!@rename($tmpOut, $targetPath)) {
                @unlink($tmpOut);
                $result['note'] = 'rename-failed-transcode';
                return $result;
            }
            @unlink($fullPath); // original (z.B. .mov) wird obsolet
            $result['path']     = $targetPath;
            $result['filename'] = basename($targetPath);
            $result['mime']     = 'video/mp4';
        }

        $result['optimized']   = true;
        $result['final_bytes'] = $newSize;
        $result['note']        = sprintf(
            'video-transcoded: %d → %d bytes (%.0f%%), %ds',
            $size, $newSize, (1 - $newSize / $size) * 100, time() - $start
        );
        return $result;
    }

    private function getMaxWidth(): int
    {
        return self::VIDEO_MAX_WIDTH;
    }

    /**
     * Führt Shell-Kommando mit Timeout aus. Kompatibel mit Windows + Linux.
     * Setzt $output (Zeilen) und $exitCode.
     */
    private function runWithTimeout(string $cmd, int $timeoutSec, array &$output, int &$exitCode): bool
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = @proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($proc)) {
            return false;
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $start       = microtime(true);
        $stdout      = '';
        $stderr      = '';
        $terminated  = false;

        while (true) {
            $status = proc_get_status($proc);
            $stdout .= stream_get_contents($pipes[1]) ?: '';
            $stderr .= stream_get_contents($pipes[2]) ?: '';

            if (!$status['running']) {
                $exitCode = $status['exitcode'];
                break;
            }

            if ((microtime(true) - $start) > $timeoutSec) {
                /* Timeout — Prozess killen */
                @proc_terminate($proc, 9);
                $terminated = true;
                $exitCode   = -1;
                break;
            }

            usleep(200_000);
        }

        $stdout .= stream_get_contents($pipes[1]) ?: '';
        $stderr .= stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        $combined = trim($stdout . "\n" . $stderr);
        $output   = $combined === '' ? [] : explode("\n", $combined);

        return !$terminated && $exitCode === 0;
    }

    /**
     * Gibt den ffmpeg-Binary-Pfad zurück.
     *
     * Auflösung in dieser Reihenfolge:
     *   1. ENV FFMPEG_BINARY (absoluter Pfad — für eigenständige Binaries)
     *   2. Projekt-lokale Binary unter /vendor/bin/ffmpeg[.exe]  (shared hosting friendly)
     *   3. System-PATH 'ffmpeg'
     *
     * Für Shared Hosting ohne installiertes ffmpeg: einfach eine statische
     * Linux-Binary unter /vendor/bin/ffmpeg ablegen (chmod +x).
     * Download z.B. von https://johnvansickle.com/ffmpeg/ (static builds).
     */
    private function getFfmpegBinary(): string
    {
        $env = getenv('FFMPEG_BINARY');
        if (is_string($env) && $env !== '' && is_executable($env)) {
            return $env;
        }

        $isWin = stripos(PHP_OS, 'WIN') === 0;
        $candidate = dirname(__DIR__, 2) . '/vendor/bin/ffmpeg' . ($isWin ? '.exe' : '');
        if (is_file($candidate) && ($isWin || is_executable($candidate))) {
            return $candidate;
        }

        return 'ffmpeg'; // falls im PATH
    }

    private function isFfmpegAvailable(): bool
    {
        if (self::$ffmpegAvailable !== null) {
            return self::$ffmpegAvailable;
        }

        $binary = $this->getFfmpegBinary();

        /* 1. Absoluter Pfad → direkter FS-Check */
        if ($binary !== 'ffmpeg' && is_file($binary)) {
            self::$ffmpegAvailable = true;
            return true;
        }

        /* 2. Im PATH? → where/command -v probieren */
        $probe = stripos(PHP_OS, 'WIN') === 0 ? 'where ffmpeg' : 'command -v ffmpeg';
        $res   = @shell_exec($probe . ' 2>&1');
        self::$ffmpegAvailable = is_string($res)
                              && trim($res) !== ''
                              && stripos($res, 'not found')    === false
                              && stripos($res, 'nicht gefunden') === false;

        return self::$ffmpegAvailable;
    }
}
