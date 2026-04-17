<?php

declare(strict_types=1);

namespace Plugins\ThemeManager;

/**
 * ThemeManager — handles theme storage, activation, listing and ZIP extraction.
 *
 * Theme structure inside a ZIP (or storage/themes/{slug}/):
 *   theme.json      — metadata
 *   theme.css       — main stylesheet (required)
 *   preview.png     — optional screenshot
 *   layout.twig     — optional custom base layout override
 */
class ThemeManager
{
    private string $themesPath;     /* writable user-installed themes (gitignored) */
    private string $bundledPath;    /* themes shipped with the plugin (deployed via git) */
    private string $activeFile;

    public function __construct()
    {
        $this->themesPath  = STORAGE_PATH . '/themes';
        $this->bundledPath = __DIR__ . '/bundled-themes';
        $this->activeFile  = STORAGE_PATH . '/themes/.active';
        if (!is_dir($this->themesPath)) {
            mkdir($this->themesPath, 0755, true);
        }
    }

    /* ── Active theme ─────────────────────────────────────── */

    public function getActive(): string
    {
        if (file_exists($this->activeFile)) {
            $slug = trim(file_get_contents($this->activeFile));
            if ($slug && $this->themeDir($slug) !== null) {
                return $slug;
            }
        }
        /* Sensible default: prefer smart-tierphysio (the SmartAdmin layout) if available */
        if ($this->themeDir('smart-tierphysio') !== null) {
            return 'smart-tierphysio';
        }
        /* Fallback: first available theme (storage or bundled) */
        $all = $this->all();
        return $all[0]['slug'] ?? 'smart-tierphysio';
    }

    public function setActive(string $slug): void
    {
        file_put_contents($this->activeFile, $slug);
    }

    /* ── Theme listing ────────────────────────────────────── */

    public function all(): array
    {
        $themes = [];

        /* Bundled themes shipped with the plugin (can't be deleted) */
        if (is_dir($this->bundledPath)) {
            foreach (new \DirectoryIterator($this->bundledPath) as $item) {
                if ($item->isDot() || !$item->isDir()) continue;
                $slug = $item->getFilename();
                $meta = $this->loadMeta($item->getPathname());
                if ($meta !== null) {
                    $meta['builtin'] = true;
                    $themes[$slug]   = $meta;
                }
            }
        }

        /* User-installed themes in storage/ override bundled themes of the same slug */
        if (is_dir($this->themesPath)) {
            foreach (new \DirectoryIterator($this->themesPath) as $item) {
                if ($item->isDot() || !$item->isDir()) continue;
                $slug = $item->getFilename();
                $meta = $this->loadMeta($item->getPathname());
                if ($meta !== null) {
                    $themes[$slug] = $meta;
                }
            }
        }

        $active = $this->getActive();
        foreach ($themes as $slug => &$t) {
            $t['slug']        = $slug;
            $t['active']      = ($slug === $active);
            $t['css_url']     = $this->cssUrl($slug);
            $t['preview_url'] = $this->previewUrl($slug);
        }

        return array_values($themes);
    }

    public function get(string $slug): ?array
    {
        foreach ($this->all() as $t) {
            if ($t['slug'] === $slug) {
                return $t;
            }
        }
        return null;
    }

    /* ── CSS path for active theme ────────────────────────── */

    public function activeCssUrl(): ?string
    {
        $active = $this->getActive();
        $dir    = $this->themeDir($active);
        if ($dir === null) return null;
        return file_exists($dir . '/theme.css')
            ? '/theme-assets/' . $active . '/theme.css'
            : null;
    }

    public function activeHasCustomLayout(): bool
    {
        return $this->activeLayoutPath() !== null;
    }

    public function activeLayoutPath(): ?string
    {
        $dir = $this->themeDir($this->getActive());
        if ($dir === null) return null;
        $path = $dir . '/layout.twig';
        return file_exists($path) ? $path : null;
    }

    /**
     * Returns the on-disk directory for a theme slug, checking storage/ first
     * (user-installed), then bundled-themes/. Returns null if not found.
     */
    public function themeDir(string $slug): ?string
    {
        $storage = $this->themesPath . '/' . $slug;
        if (is_dir($storage)) return $storage;
        $bundled = $this->bundledPath . '/' . $slug;
        if (is_dir($bundled)) return $bundled;
        return null;
    }

    public function isBundled(string $slug): bool
    {
        return !is_dir($this->themesPath . '/' . $slug)
            && is_dir($this->bundledPath . '/' . $slug);
    }

    /* ── ZIP upload & extraction ──────────────────────────── */

    /**
     * @throws \RuntimeException on any error
     */
    public function installFromZip(string $tmpPath, string $originalName): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('ZipArchive ist auf diesem Server nicht verfügbar.');
        }

        $zip = new \ZipArchive();
        $res = $zip->open($tmpPath);
        if ($res !== true) {
            throw new \RuntimeException('ZIP-Datei konnte nicht geöffnet werden (Code: ' . $res . ').');
        }

        /* Find theme.json inside the ZIP (may be in a sub-folder) */
        $themeJsonIndex = false;
        $prefix = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (basename($name) === 'theme.json') {
                $themeJsonIndex = $i;
                $prefix = ($name === 'theme.json') ? '' : dirname($name) . '/';
                break;
            }
        }

        if ($themeJsonIndex === false) {
            $zip->close();
            throw new \RuntimeException('theme.json nicht im ZIP gefunden. Bitte prüfe die Struktur des Theme-Pakets.');
        }

        $metaJson = $zip->getFromIndex($themeJsonIndex);
        $meta     = json_decode($metaJson, true);
        if (!is_array($meta) || empty($meta['slug'])) {
            $zip->close();
            throw new \RuntimeException('theme.json ist ungültig oder enthält kein "slug"-Feld.');
        }

        $slug = preg_replace('/[^a-z0-9\-_]/', '', strtolower($meta['slug']));
        if (empty($slug)) {
            $zip->close();
            throw new \RuntimeException('Ungültiger Theme-Slug.');
        }
        if ($slug === 'default') {
            $zip->close();
            throw new \RuntimeException('Der Slug "default" ist reserviert und kann nicht verwendet werden.');
        }

        /* Check theme.css exists in ZIP */
        $cssStat = $zip->statName($prefix . 'theme.css');
        if ($cssStat === false) {
            $zip->close();
            throw new \RuntimeException('theme.css nicht im ZIP gefunden.');
        }

        /* Extract into storage/themes/{slug}/ */
        $destDir = $this->themesPath . '/' . $slug;
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        /* Extract only files under the detected prefix */
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name    = $zip->getNameIndex($i);
            $relName = $prefix !== '' ? (str_starts_with($name, $prefix) ? substr($name, strlen($prefix)) : null) : $name;
            if ($relName === null || $relName === '' || str_ends_with($relName, '/')) {
                continue;
            }
            /* Only allow safe filenames */
            if (preg_match('/\.\./', $relName)) {
                continue;
            }
            $allowed = ['theme.json', 'theme.css', 'preview.png', 'preview.jpg', 'preview.svg', 'layout.twig', 'README.md'];
            if (!in_array($relName, $allowed, true) && !str_starts_with($relName, 'assets/')) {
                continue;
            }
            $destFile = $destDir . '/' . $relName;
            $destSubDir = dirname($destFile);
            if (!is_dir($destSubDir)) {
                mkdir($destSubDir, 0755, true);
            }
            file_put_contents($destFile, $zip->getFromIndex($i));
        }

        $zip->close();

        return $slug;
    }

    /* ── Delete a theme ───────────────────────────────────── */

    public function delete(string $slug): void
    {
        if ($this->isBundled($slug)) {
            throw new \RuntimeException('Gebündelte Themes (Material Pro, SmartAdmin etc.) können nicht gelöscht werden.');
        }
        if ($this->getActive() === $slug) {
            throw new \RuntimeException('Das aktive Theme kann nicht gelöscht werden. Bitte zuerst ein anderes Theme aktivieren.');
        }
        $dir = $this->themesPath . '/' . $slug;
        if (is_dir($dir)) {
            $this->removeDir($dir);
        }
    }

    /* ── Helpers ──────────────────────────────────────────── */

    private function loadMeta(string $dir): ?array
    {
        $jsonFile = $dir . '/theme.json';
        if (!file_exists($jsonFile)) {
            return null;
        }
        $cssFile = $dir . '/theme.css';
        if (!file_exists($cssFile)) {
            return null;
        }
        $raw = file_get_contents($jsonFile);
        if ($raw === false) {
            return null;
        }
        /* Strip UTF-8 / UTF-16 BOM which would break json_decode() */
        $raw = preg_replace('/^(\xEF\xBB\xBF|\xFE\xFF|\xFF\xFE)/', '', $raw);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }
        return $data;
    }

    private function cssUrl(string $slug): ?string
    {
        $dir = $this->themeDir($slug);
        if ($dir === null) return null;
        return file_exists($dir . '/theme.css') ? '/theme-assets/' . $slug . '/theme.css' : null;
    }

    private function previewUrl(string $slug): ?string
    {
        $dir = $this->themeDir($slug);
        if ($dir === null) return null;
        foreach (['preview.png', 'preview.jpg', 'preview.svg'] as $f) {
            if (file_exists($dir . '/' . $f)) {
                return '/theme-assets/' . $slug . '/' . $f;
            }
        }
        return null;
    }

    private function removeDir(string $dir): void
    {
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        ) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
