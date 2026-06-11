<?php

namespace App\Services;

use App\Models\Angle;
use App\Models\Template;

class AngleTemplateMergeService
{
    private const BODY_PLACEHOLDER_PATTERN = '/<!--INTERNAL--(?<id>BD\d+)--EXTERNAL-->/';

    /**
     * Merge angle HTML bodies into a theme shell (same logic as new landing page creation).
     *
     * @return array{main_html: string, main_css: string, main_js: string}
     */
    public function merge(Angle $angle, Template $template): array
    {
        $allBodies = $angle->contents()->where('type', 'html')->get();
        $mainHtml = (string) $template->index;

        $mainCss = '';
        $template->contents()->where('type', 'css')->get()->each(function ($item) use (&$mainCss) {
            $mainCss .= $item->content;
        });

        $mainJs = '';
        $template->contents()->where('type', 'js')->get()->each(function ($item) use (&$mainJs) {
            $mainJs .= $item->content."\n";
        });

        foreach ($allBodies as $key => $body) {
            $bodyKey = $key + 1;
            $mainHtml = str_replace("<!--INTERNAL--BD$bodyKey--EXTERNAL-->", $body->content, $mainHtml);
        }

        $mainHtml = $this->rewriteTemplateImagePaths($mainHtml, $template);
        $mainCss = $this->rewriteTemplateFontPaths($mainCss, $template);

        $mainHtml = preg_replace(
            '/src="angle_images\//',
            'src="../../storage/angles/'.$angle->uuid.'/images/'.$angle->asset_unique_uuid.'-',
            $mainHtml
        );

        return [
            'main_html' => $mainHtml,
            'main_css' => $mainCss,
            'main_js' => $mainJs,
        ];
    }

    /**
     * Change theme on an existing landing page: new theme shell + styles, keep current body content/images.
     *
     * @return array{
     *     main_html: string,
     *     main_css: string,
     *     main_js: string,
     *     content_preserved: bool,
     *     mapping_status: string,
     *     source_repeated_bds: array<string, int>,
     *     target_repeated_bds: array<string, int>,
     *     preserved_asset_paths: array<int, string>
     * }
     */
    public function changeThemePreservingContent(
        Angle $angle,
        string $currentMainHtml,
        Template $oldTemplate,
        Template $newTemplate
    ): array {
        $oldShellForMatching = $this->buildTemplateShellForMergedComparison($oldTemplate);
        $sourceUsage = $this->placeholderUsage($oldShellForMatching);
        $targetUsage = $this->placeholderUsage((string) $newTemplate->index);
        $bodies = $this->extractBodySegmentsFromMergedHtml($oldShellForMatching, $currentMainHtml);
        if ($bodies === null || $bodies === []) {
            // Browsers/editors may decode harmless entities such as &times; when saving HTML.
            $decodedOldShell = html_entity_decode($oldShellForMatching, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $bodies = $this->extractBodySegmentsFromMergedHtml($decodedOldShell, $currentMainHtml);
        }
        if ($bodies === null || $bodies === []) {
            $bodies = $this->extractBodySegmentsWithRegexAnchors($oldShellForMatching, $currentMainHtml);
        }
        $contentPreserved = $bodies !== null && $bodies !== [];

        if ($contentPreserved) {
            $sanitizedBodies = array_map(
                fn (string $body) => $this->wrapBodyForThemeLayout(
                    $this->sanitizeBodySegment($this->removeTemplateAssetElements($body, $oldTemplate))
                ),
                $bodies
            );
            $preservedHtml = implode('', $sanitizedBodies);
            $mainHtml = $this->injectBodySegmentsIntoShell((string) $newTemplate->index, $sanitizedBodies);
            $mainHtml = $this->rewriteTemplateImagePaths($mainHtml, $newTemplate);
            $layoutGuardCss = $this->themeChangeLayoutGuardCss();
        } else {
            // Safe fallback: preserve current landing HTML as-is (keeps current language/content/images).
            $preservedHtml = $currentMainHtml;
            $mainHtml = '<div class="lp-theme-safe-content">'.$currentMainHtml.'</div>';
            $layoutGuardCss = $this->themeChangeFallbackGuardCss();
        }

        $styles = $this->themeStylesOnly($newTemplate);
        $mainCss = $styles['main_css']."\n".$layoutGuardCss;

        return [
            'main_html' => $mainHtml,
            'main_css' => $mainCss,
            'main_js' => $styles['main_js'],
            'content_preserved' => $contentPreserved,
            'mapping_status' => $contentPreserved ? 'bd_mapped' : 'safe_fallback',
            'source_repeated_bds' => $sourceUsage['repeated'],
            'target_repeated_bds' => $targetUsage['repeated'],
            'preserved_asset_paths' => $this->publicStorageAssetPaths($preservedHtml),
        ];
    }

    /**
     * Fallback extractor:
     * Build a tolerant regex from old shell static anchors and capture body sections between placeholders.
     *
     * @return array<string, string>|null
     */
    private function extractBodySegmentsWithRegexAnchors(string $templateIndexWithPlaceholders, string $mergedHtml): ?array
    {
        $placeholderIds = $this->extractPlaceholderIds($templateIndexWithPlaceholders);
        if ($placeholderIds === null) {
            return null;
        }

        preg_match_all(self::BODY_PLACEHOLDER_PATTERN, $templateIndexWithPlaceholders, $matches, PREG_OFFSET_CAPTURE);
        $placeholders = $matches[0];

        $staticParts = [];
        $lastEnd = 0;
        foreach ($placeholders as $placeholder) {
            $staticParts[] = substr($templateIndexWithPlaceholders, $lastEnd, $placeholder[1] - $lastEnd);
            $lastEnd = $placeholder[1] + strlen($placeholder[0]);
        }
        $staticParts[] = substr($templateIndexWithPlaceholders, $lastEnd);

        $pattern = '/^';
        $totalParts = count($staticParts);
        for ($i = 0; $i < $totalParts; $i++) {
            $pattern .= $this->buildFlexibleQuotedSegmentPattern($staticParts[$i]);
            if ($i < $totalParts - 1) {
                $pattern .= '(.*?)';
            }
        }
        $pattern .= '$/is';

        $matched = @preg_match($pattern, $mergedHtml, $captures);
        if ($matched !== 1) {
            return null;
        }

        $segments = [];
        for ($i = 1; $i <= count($placeholders); $i++) {
            $segments[] = $captures[$i] ?? '';
        }

        return $this->mapSegmentsToBdIds($placeholderIds, $segments);
    }

    private function buildFlexibleQuotedSegmentPattern(string $segment): string
    {
        $quoted = preg_quote($segment, '/');
        // Make whitespace tolerant so pretty-print/minify differences do not break extraction.
        $quoted = preg_replace('/(?:\\\\[rnt]|\\\\ )+/', '\\\\s*', $quoted);
        // Tolerate accidental spacing between tags.
        $quoted = str_replace('\\>\\<', '\\>\\s*\\<', $quoted);

        return $quoted;
    }

    /**
     * Extract body segments from merged HTML using the old theme shell as a map.
     *
     * @return array<string, string>|null
     */
    public function extractBodySegmentsFromMergedHtml(string $templateIndexWithPlaceholders, string $mergedHtml): ?array
    {
        $placeholderIds = $this->extractPlaceholderIds($templateIndexWithPlaceholders);
        if ($placeholderIds === null) {
            return null;
        }

        preg_match_all(self::BODY_PLACEHOLDER_PATTERN, $templateIndexWithPlaceholders, $matches, PREG_OFFSET_CAPTURE);
        $placeholders = $matches[0];

        $staticParts = [];
        $lastEnd = 0;

        foreach ($placeholders as $placeholder) {
            $staticParts[] = substr($templateIndexWithPlaceholders, $lastEnd, $placeholder[1] - $lastEnd);
            $lastEnd = $placeholder[1] + strlen($placeholder[0]);
        }
        $staticParts[] = substr($templateIndexWithPlaceholders, $lastEnd);

        $segments = [];
        $pos = 0;

        for ($i = 0; $i < count($placeholders); $i++) {
            $prefix = $staticParts[$i];
            if ($prefix !== '') {
                $prefixPos = strpos($mergedHtml, $prefix, $pos);
                if ($prefixPos === false) {
                    return null;
                }
                $pos = $prefixPos + strlen($prefix);
            }

            $suffix = $staticParts[$i + 1];
            if ($suffix !== '') {
                $suffixPos = strpos($mergedHtml, $suffix, $pos);
                if ($suffixPos === false) {
                    return null;
                }
                $segments[] = substr($mergedHtml, $pos, $suffixPos - $pos);
                $pos = $suffixPos;
            } else {
                $segments[] = substr($mergedHtml, $pos);
            }
        }

        return $this->mapSegmentsToBdIds($placeholderIds, $segments);
    }

    /**
     * @param  array<string, string>  $bodies
     */
    public function injectBodySegmentsIntoShell(string $shell, array $bodies): string
    {
        foreach ($bodies as $bdId => $body) {
            $placeholder = "<!--INTERNAL--{$bdId}--EXTERNAL-->";
            if (str_contains($shell, $placeholder)) {
                $shell = str_replace($placeholder, $body, $shell);
            }
        }

        return (string) preg_replace(self::BODY_PLACEHOLDER_PATTERN, '', $shell);
    }

    /**
     * @return array{sequence: array<int, string>, counts: array<string, int>, repeated: array<string, int>}
     */
    public function placeholderUsage(string $shell): array
    {
        $sequence = $this->extractPlaceholderIds($shell) ?? [];
        $counts = array_count_values($sequence);

        return [
            'sequence' => $sequence,
            'counts' => $counts,
            'repeated' => array_filter($counts, fn (int $count) => $count > 1),
        ];
    }

    /**
     * Return local public-disk asset paths referenced by preserved content.
     *
     * @return array<int, string>
     */
    public function publicStorageAssetPaths(string $html): array
    {
        preg_match_all(
            '/\b(?:src|poster)=["\'](?<path>(?:\.\.\/)*\/?storage\/[^"\']+)["\']/i',
            $html,
            $matches
        );

        $paths = array_map(
            fn (string $path) => preg_replace('#^(?:\.\./)*/?storage/#', '', $path),
            $matches['path'] ?? []
        );

        return array_values(array_unique(array_filter($paths)));
    }

    /**
     * Remove media owned by the old theme so its decoration is not carried into the new theme.
     */
    public function removeTemplateAssetElements(string $html, Template $template): string
    {
        $templatePath = preg_quote('storage/templates/'.$template->uuid.'/', '#');

        return (string) preg_replace(
            '#<(?:img|source|video)\b[^>]*\b(?:src|poster)=["\'](?:\.\./)*/?'.$templatePath.'[^"\']*["\'][^>]*>\s*</video\s*>|'
            .'<(?:img|source)\b[^>]*\bsrc=["\'](?:\.\./)*/?'.$templatePath.'[^"\']*["\'][^>]*>#i',
            '',
            $html
        );
    }

    /**
     * @return array<int, string>|null
     */
    private function extractPlaceholderIds(string $shell): ?array
    {
        if (! preg_match_all(self::BODY_PLACEHOLDER_PATTERN, $shell, $matches)) {
            return null;
        }

        return $matches['id'] ?: null;
    }

    /**
     * Repeated placeholders are safe only when every occurrence contains the same HTML.
     *
     * @param  array<int, string>  $placeholderIds
     * @param  array<int, string>  $segments
     * @return array<string, string>|null
     */
    private function mapSegmentsToBdIds(array $placeholderIds, array $segments): ?array
    {
        if (count($placeholderIds) !== count($segments)) {
            return null;
        }

        $bodies = [];

        foreach ($placeholderIds as $index => $bdId) {
            $segment = trim($segments[$index]);

            if (array_key_exists($bdId, $bodies) && trim($bodies[$bdId]) !== $segment) {
                return null;
            }

            $bodies[$bdId] = $segment;
        }

        return $bodies;
    }

    /**
     * Theme shell styles only (fallback when body extraction is not possible).
     *
     * @return array{main_css: string, main_js: string}
     */
    public function themeStylesOnly(Template $template): array
    {
        $mainCss = '';
        $template->contents()->where('type', 'css')->get()->each(function ($item) use (&$mainCss) {
            $mainCss .= $item->content;
        });

        $mainJs = '';
        $template->contents()->where('type', 'js')->get()->each(function ($item) use (&$mainJs) {
            $mainJs .= $item->content."\n";
        });

        $mainCss = $this->rewriteTemplateFontPaths($mainCss, $template);

        return [
            'main_css' => $mainCss,
            'main_js' => $mainJs,
        ];
    }

    private function rewriteTemplateImagePaths(string $html, Template $template): string
    {
        return (string) preg_replace(
            '/src="template_images\//',
            'src="../../storage/templates/'.$template->uuid.'/images/'.$template->asset_unique_uuid.'-',
            $html
        );
    }

    private function rewriteTemplateFontPaths(string $css, Template $template): string
    {
        return (string) preg_replace(
            '/fonts\//',
            '../../storage/templates/'.$template->uuid.'/fonts/'.$template->asset_unique_uuid.'-',
            $css
        );
    }

    /**
     * Convert template shell placeholders to the same path format used in saved merged landing HTML.
     * This makes body extraction robust during theme change.
     */
    private function buildTemplateShellForMergedComparison(Template $template): string
    {
        return $this->rewriteTemplateImagePaths((string) $template->index, $template);
    }

    /**
     * Remove preview-editor full-width styles and legacy theme wrapper divs from a body segment.
     */
    public function sanitizeBodySegment(string $body): string
    {
        $body = trim($body);
        if ($body === '') {
            return $body;
        }

        $body = (string) preg_replace('/\sstyle="\s*"/i', '', $body);

        foreach (['main_parent_container', 'main-container', 'main-con-box'] as $className) {
            $pattern = '/^<div[^>]*class="[^"]*\\b'
                .preg_quote($className, '/')
                .'\\b[^"]*"[^>]*>(.*)<\\/div>\\s*$/is';

            if (preg_match($pattern, $body, $matches)) {
                $body = trim($matches[1]);
            }
        }

        return $body;
    }

    /**
     * Center article content when the new theme shell does not constrain body width.
     */
    public function wrapBodyForThemeLayout(string $body): string
    {
        $body = trim($body);
        if ($body === '' || str_contains($body, 'lp-theme-body-inner')) {
            return $body;
        }

        return '<div class="lp-theme-body-inner">'.$body.'</div>';
    }

    /**
     * Layout guard appended after theme change so images/text are not forced to viewport width.
     */
    public function themeChangeLayoutGuardCss(): string
    {
        return <<<'CSS'
.lp-theme-body-inner {
    max-width: 1140px;
    margin-left: auto;
    margin-right: auto;
    width: 100%;
    box-sizing: border-box;
    padding-left: 16px;
    padding-right: 16px;
}
.lp-theme-body-inner img {
    max-width: 100% !important;
    height: auto !important;
    width: auto !important;
}
.lp-theme-body-inner h1,
.lp-theme-body-inner h2,
.lp-theme-body-inner h3,
.lp-theme-body-inner p {
    max-width: 100%;
}
CSS;
    }

    /**
     * Stronger guard used only when body remapping fails and we preserve current HTML wholesale.
     * Prevents unreadable one-word-per-line titles and hidden/oversized images from incompatible theme styles.
     */
    public function themeChangeFallbackGuardCss(): string
    {
        return <<<'CSS'
.lp-theme-safe-content {
    max-width: 1140px;
    margin-left: auto;
    margin-right: auto;
    width: 100%;
    box-sizing: border-box;
    padding-left: 16px;
    padding-right: 16px;
}
.lp-theme-safe-content img {
    display: block !important;
    max-width: 100% !important;
    width: auto !important;
    height: auto !important;
}
.lp-theme-safe-content h1,
.lp-theme-safe-content h2,
.lp-theme-safe-content h3,
.lp-theme-safe-content h4,
.lp-theme-safe-content p,
.lp-theme-safe-content span,
.lp-theme-safe-content div {
    max-width: none !important;
    word-break: normal !important;
    overflow-wrap: break-word !important;
    white-space: normal !important;
}
CSS;
    }
}
