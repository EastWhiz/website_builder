<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LandingPageAssetService
{
    private const STORAGE_REFERENCE_PATTERN = '~(?:(?:https?:)?//[^/"\'()<>\s]+)?(?:/|\.\./)+storage/(?:angles|templates|angleTemplates|angleContents)/[^"\'()<>]*?\.(?:avif|bmp|css|eot|gif|ico|jpe?g|js|mov|mp4|otf|pdf|png|svg|tiff?|ttf|webm|webp|woff2?)(?:\?[^"\'()<>\s]*)?(?:\#[^"\'()<>\s]*)?~i';

    private const QUOTED_STORAGE_REFERENCE_PATTERN = '~(?:(?:https?:)?//[^/"\'()<>\s]+)?(?:/|\.\./)+storage/(?:angles|templates|angleTemplates|angleContents)/[^"\'<>\r\n]+(?=["\'])~i';

    /**
     * @param  array<int, string|null>  $contents
     * @param  array<int, string|null>  $knownPaths
     * @return array<int, string>
     */
    public function collectStoragePaths(array $contents, array $knownPaths = []): array
    {
        $paths = [];

        foreach ($knownPaths as $path) {
            $relative = $this->normalizeStoragePath($path);
            if ($relative !== null) {
                $paths[$relative] = $relative;
            }
        }

        foreach ($contents as $content) {
            if (! $content) {
                continue;
            }

            foreach ($this->referencePatterns() as $pattern) {
                if (! preg_match_all($pattern, $content, $matches)) {
                    continue;
                }

                foreach ($matches[0] as $reference) {
                    $relative = $this->normalizeStoragePath($reference);
                    if ($relative !== null) {
                        $paths[$relative] = $relative;
                    }
                }
            }
        }

        return array_values($paths);
    }

    public function normalizeStoragePath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $path = html_entity_decode(trim($path), ENT_QUOTES | ENT_HTML5);
        $path = preg_replace('~[?#].*$~', '', $path);
        $storagePosition = stripos($path, '/storage/');

        if ($storagePosition !== false) {
            $path = substr($path, $storagePosition + strlen('/storage/'));
        } else {
            $path = preg_replace('~^(?:\.\./)+storage/~i', '', $path);
            $path = preg_replace('~^/?storage/~i', '', $path);
        }

        $path = ltrim(rawurldecode((string) $path), '/');

        if (! preg_match('~^(angles|templates|angleTemplates|angleContents)/~i', $path)) {
            return null;
        }

        return $path;
    }

    /**
     * @param  array<string, string>  $replacements
     */
    public function rewriteStorageReferences(string $content, array $replacements): string
    {
        if ($content === '' || $replacements === []) {
            return $content;
        }

        foreach ($this->referencePatterns() as $pattern) {
            $content = (string) preg_replace_callback(
                $pattern,
                function (array $match) use ($replacements) {
                    $relative = $this->normalizeStoragePath($match[0]);

                    return $relative !== null && isset($replacements[$relative])
                        ? $replacements[$relative]
                        : $match[0];
                },
                $content
            );
        }

        return $content;
    }

    /**
     * Copy all known and referenced local page assets into the cloned page's own folder.
     *
     * @param  array<int, string|null>  $contents
     * @param  array<int, string|null>  $knownPaths
     * @return array{replacements: array<string, string>, missing: array<int, string>}
     */
    public function copyAssetsForClone(string $targetUuid, array $contents, array $knownPaths = []): array
    {
        $disk = Storage::disk('public');
        $replacements = [];
        $missing = [];
        $sourcePaths = $this->collectStoragePaths($contents, $knownPaths);

        foreach ($sourcePaths as $sourcePath) {
            if (! $disk->exists($sourcePath)) {
                $missing[] = $sourcePath;
            }
        }

        if ($missing !== []) {
            Log::warning('Landing page clone preserved references to unavailable local assets', [
                'target_uuid' => $targetUuid,
                'missing_assets' => $missing,
            ]);
        }

        foreach ($sourcePaths as $sourcePath) {
            if (! $disk->exists($sourcePath)) {
                continue;
            }

            $destinationPath = 'angleTemplates/'.$targetUuid.'/assets/'
                .substr(sha1($sourcePath), 0, 16).'/'.basename($sourcePath);
            if (! $disk->copy($sourcePath, $destinationPath)) {
                throw new \RuntimeException('The landing page cannot be cloned because an asset could not be copied.');
            }
            $replacements[$sourcePath] = '../../storage/'.$destinationPath;
        }

        return ['replacements' => $replacements, 'missing' => $missing];
    }

    /**
     * @param  array<int, string|null>  $contents
     * @param  array<int, string|null>  $knownPaths
     * @return array{paths: array<int, string>, replacements: array<string, string>, missing: array<int, string>}
     */
    public function prepareAssetsForExport(array $contents, array $knownPaths = []): array
    {
        $disk = Storage::disk('public');
        $paths = [];
        $replacements = [];
        $missing = [];

        foreach ($this->collectStoragePaths($contents, $knownPaths) as $relativePath) {
            if (! $disk->exists($relativePath)) {
                $missing[] = $relativePath;

                continue;
            }

            $paths[] = $relativePath;
            $replacements[$relativePath] = 'assets/'.$relativePath;
        }

        if ($missing !== []) {
            Log::warning('Landing page export contains missing local assets', [
                'missing_assets' => $missing,
            ]);
        }

        return [
            'paths' => $paths,
            'replacements' => $replacements,
            'missing' => $missing,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function referencePatterns(): array
    {
        return [
            self::STORAGE_REFERENCE_PATTERN,
            self::QUOTED_STORAGE_REFERENCE_PATTERN,
        ];
    }
}
