<?php

namespace App\Services;

use App\Models\Angle;
use App\Models\AngleTemplate;
use App\Models\Template;

class AngleTemplateMergeService
{
    private const BODY_PLACEHOLDER_PATTERN = '/<!--INTERNAL--(?<id>BD\d+(?:_[A-Z][A-Z0-9_]*)?)--EXTERNAL-->/';

    private const BODY_ID_PATTERN = '/^BD\d+$/';

    private const SUB_SLOT_ID_PATTERN = '/^BD\d+_[A-Z][A-Z0-9_]*$/';

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

        $mainHtml = $this->injectBodySegmentsIntoShell(
            $mainHtml,
            $this->mapAngleBodiesByIdentifier($allBodies, true)
        );

        $mainHtml = $this->rewriteTemplateImagePaths($mainHtml, $template);
        $mainCss = $this->rewriteTemplateFontPaths($mainCss, $template)."\n".$this->legacyBdDefaultContentCss();

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
     *     target_sub_slots: array<int, string>,
     *     unresolved_sub_slots: array<int, string>,
     *     unresolved_body_ids: array<int, string>,
     *     preserved_asset_paths: array<int, string>
     * }
     */
    public function changeThemePreservingContent(
        Angle $angle,
        string $currentMainHtml,
        Template $oldTemplate,
        Template $newTemplate,
        string $currentMainCss = '',
        string $currentMainJs = ''
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
        $targetSubSlots = $this->subSlotIds((string) $newTemplate->index);

        $unresolvedSubSlots = $bodies === null
            ? $targetSubSlots
            : $this->missingSubSlotIds((string) $newTemplate->index, $bodies);
        $unresolvedBodyIds = $bodies === null
            ? []
            : $this->missingPlainBodiesRequiredBySplitSource((string) $newTemplate->index, $bodies);
        $contentPreserved = $bodies !== null
            && $bodies !== []
            && $unresolvedSubSlots === []
            && $unresolvedBodyIds === [];

        if ($contentPreserved) {
            $sanitizedBodies = [];
            foreach ($bodies as $bodyId => $body) {
                $sanitizedBody = $this->sanitizeBodySegment(
                    $this->removeTemplateAssetElements($body, $oldTemplate)
                );
                $sanitizedBodies[$bodyId] = $sanitizedBody;
            }
            $preservedHtml = implode('', $sanitizedBodies);
            $mainHtml = $this->injectBodySegmentsIntoShell((string) $newTemplate->index, $sanitizedBodies);
            $mainHtml = $this->rewriteTemplateImagePaths($mainHtml, $newTemplate);
            $mainHtml = $this->rewriteAngleImagePaths($mainHtml, $angle);
            $layoutGuardCss = '';
            $styles = $this->themeStylesOnly($newTemplate);
        } else {
            // Safe fallback: preserve current landing HTML/CSS/JS together so layout and icons stay intact.
            $preservedHtml = $currentMainHtml;
            $mainHtml = '<div class="lp-theme-safe-content">'.$currentMainHtml.'</div>';
            $layoutGuardCss = $this->themeChangeFallbackGuardCss();
            $styles = [
                'main_css' => $currentMainCss,
                'main_js' => $currentMainJs,
            ];
        }

        $mainCss = $styles['main_css']."\n".$layoutGuardCss;

        return [
            'main_html' => $mainHtml,
            'main_css' => $mainCss,
            'main_js' => $styles['main_js'],
            'content_preserved' => $contentPreserved,
            'mapping_status' => $contentPreserved
                ? ($targetSubSlots === [] ? 'bd_mapped' : 'slot_mapped')
                : 'safe_fallback',
            'source_repeated_bds' => $sourceUsage['repeated'],
            'target_repeated_bds' => $targetUsage['repeated'],
            'target_sub_slots' => $targetSubSlots,
            'unresolved_sub_slots' => $unresolvedSubSlots,
            'unresolved_body_ids' => $unresolvedBodyIds,
            'preserved_asset_paths' => $this->publicStorageAssetPaths($preservedHtml),
        ];
    }

    /**
     * Render saved structured BD rows into a landing page's current theme.
     *
     * @return array{main_html: string, main_css: string, main_js: string}
     */
    public function renderStructuredBd(AngleTemplate $angleTemplate): array
    {
        $template = $angleTemplate->template;
        if (!$template) {
            throw new \RuntimeException('Structured landing page cannot render without a theme.');
        }

        $bodies = $angleTemplate->bdContents()
            ->pluck('content', 'slot_key')
            ->map(fn ($content) => (string) $content)
            ->all();

        return $this->renderStructuredBodies($template, $bodies, $angleTemplate->angle);
    }

    /**
     * @param  array<string, string>  $bodies
     * @return array{main_html: string, main_css: string, main_js: string}
     */
    public function renderStructuredBodies(Template $template, array $bodies, ?Angle $angle = null): array
    {
        $bodies = array_map(fn ($body) => $this->normalizeStructuredBodyHtml((string) $body), $bodies);
        $bodies = $this->wrapStructuredBodiesForScopedStyling($bodies);
        $mainHtml = $this->injectBodySegmentsIntoShell((string) $template->index, $bodies);
        $mainHtml = $this->rewriteTemplateImagePaths($mainHtml, $template);

        if ($angle) {
            $mainHtml = $this->rewriteAngleImagePaths($mainHtml, $angle);
        }

        $styles = $this->themeStylesOnly($template);

        return [
            'main_html' => $mainHtml,
            'main_css' => $styles['main_css']."\n".$this->structuredBdDefaultContentCss(),
            'main_js' => $styles['main_js'],
        ];
    }

    /**
     * Refresh BD wrapper content in an already-rendered structured page.
     * This preserves page-level additions that users inserted around BD slots.
     *
     * @param  array<string, string>  $bodies
     */
    public function refreshStructuredBodySlotsInRenderedHtml(string $html, array $bodies, ?Angle $angle = null): string
    {
        if (! str_contains($html, 'lp-structured-bd-slot')) {
            return $html;
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8"><!DOCTYPE html><html><body><div id="lp-root">'.$html.'</div></body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return $html;
        }

        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " lp-structured-bd-slot ")]');
        if (! $nodes) {
            return $html;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof \DOMElement) {
                continue;
            }

            $slotKey = $node->getAttribute('data-bd-slot');
            if ($slotKey === '' && preg_match('/(?:^|\s)lp-structured-bd-slot-([A-Z0-9_-]+)(?:\s|$)/i', $node->getAttribute('class'), $matches)) {
                $slotKey = strtoupper($matches[1]);
            }

            if ($slotKey === '' || ! array_key_exists($slotKey, $bodies)) {
                continue;
            }

            $body = $this->normalizeStructuredBodyHtml((string) $bodies[$slotKey]);
            if ($angle) {
                $body = $this->rewriteAngleImagePaths($body, $angle);
            }

            $this->replaceElementInnerHtml($dom, $node, $body);
        }

        $root = $dom->getElementById('lp-root');
        if (! $root) {
            return $html;
        }

        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }

        return $result;
    }

    /**
     * @param  array<string, string>  $bodies
     * @return array<string, string>
     */
    private function wrapStructuredBodiesForScopedStyling(array $bodies): array
    {
        return collect($bodies)
            ->mapWithKeys(function (string $body, string $slotKey) {
                $safeSlotKey = preg_replace('/[^A-Z0-9_-]/i', '', $slotKey) ?: 'BD';

                return [
                    $slotKey => '<div class="lp-structured-bd-slot lp-structured-bd-slot-'.$safeSlotKey.'" data-bd-slot="'.$safeSlotKey.'">'.$body.'</div>',
                ];
            })
            ->all();
    }

    private function replaceElementInnerHtml(\DOMDocument $dom, \DOMElement $element, string $html): void
    {
        while ($element->firstChild) {
            $element->removeChild($element->firstChild);
        }

        $fragmentDom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $fragmentDom->loadHTML(
            '<?xml encoding="UTF-8"><!DOCTYPE html><html><body><div id="fragment-root">'.$html.'</div></body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            $element->appendChild($dom->createTextNode($html));
            return;
        }

        $fragmentRoot = $fragmentDom->getElementById('fragment-root');
        if (! $fragmentRoot) {
            return;
        }

        foreach (iterator_to_array($fragmentRoot->childNodes) as $child) {
            $element->appendChild($dom->importNode($child, true));
        }
    }

    private function structuredBdDefaultContentCss(): string
    {
        return <<<'CSS'
.lp-structured-bd-slot {
    display: contents;
}
CSS;
    }

    private function legacyBdDefaultContentCss(): string
    {
        return <<<'CSS'
h1.lp-legacy-bd-heading {
    display: block;
    font-size: 2em;
    font-weight: 700;
    line-height: 1.2;
    margin: 0.67em 0;
}
h2.lp-legacy-bd-heading {
    display: block;
    font-size: 1.5em;
    font-weight: 700;
    line-height: 1.25;
    margin: 0.83em 0;
}
h3.lp-legacy-bd-heading {
    display: block;
    font-size: 1.17em;
    font-weight: 700;
    line-height: 1.3;
    margin: 1em 0;
}
CSS;
    }

    private function normalizeStructuredBodyHtml(string $body): string
    {
        return $this->normalizeBodyHtml($body);
    }

    private function normalizeBodyHtml(string $body): string
    {
        $body = trim($body);

        if (strlen($body) >= 2) {
            $first = $body[0];
            $last = substr($body, -1);
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $body = substr($body, 1, -1);
            }
        }

        if (str_contains($body, '&lt;') || str_contains($body, '&gt;')) {
            $body = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $body;
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
    public function injectBodySegmentsIntoShell(string $shell, array $bodies, bool $removeUnmatched = true): string
    {
        foreach ($bodies as $bdId => $body) {
            $placeholder = "<!--INTERNAL--{$bdId}--EXTERNAL-->";
            if (str_contains($shell, $placeholder)) {
                $shell = str_replace($placeholder, $body, $shell);
            }
        }

        return $removeUnmatched
            ? (string) preg_replace(self::BODY_PLACEHOLDER_PATTERN, '', $shell)
            : $shell;
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
     * @return array<int, string>
     */
    public function subSlotIds(string $shell): array
    {
        return array_values(array_unique(array_filter(
            $this->extractPlaceholderIds($shell) ?? [],
            fn (string $id) => $this->isSubSlotId($id)
        )));
    }

    /**
     * @param  array<string, string>  $bodies
     * @return array<int, string>
     */
    public function missingSubSlotIds(string $shell, array $bodies): array
    {
        return array_values(array_diff($this->subSlotIds($shell), array_keys($bodies)));
    }

    /**
     * A split source cannot safely reconstruct a full plain BD without explicit full-BD content.
     *
     * @param  array<string, string>  $bodies
     * @return array<int, string>
     */
    public function missingPlainBodiesRequiredBySplitSource(string $targetShell, array $bodies): array
    {
        $targetPlainBodies = array_values(array_unique(array_filter(
            $this->extractPlaceholderIds($targetShell) ?? [],
            fn (string $id) => (bool) preg_match(self::BODY_ID_PATTERN, $id)
        )));
        $splitBaseBodies = array_values(array_unique(array_map(
            fn (string $id) => explode('_', $id, 2)[0],
            array_filter(array_keys($bodies), fn (string $id) => $this->isSubSlotId($id))
        )));

        return array_values(array_filter(
            $targetPlainBodies,
            fn (string $id) => in_array($id, $splitBaseBodies, true) && ! array_key_exists($id, $bodies)
        ));
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
     * Prefer explicit AngleContent names while retaining positional BD1-BD5 compatibility.
     *
     * @param  iterable<int, object>  $allBodies
     * @return array<string, string>
     */
    public function mapAngleBodiesByIdentifier(iterable $allBodies, bool $decorateLegacyHeadings = false): array
    {
        $bodies = [];
        $legacyBodies = [];

        foreach ($allBodies as $index => $body) {
            $content = $this->normalizeBodyHtml((string) ($body->content ?? ''));
            if ($decorateLegacyHeadings) {
                $content = $this->decorateLegacyBdHeadings($content);
            }
            $name = $this->canonicalBodyIdentifier((string) ($body->name ?? ''));

            if ($name !== null) {
                $bodies[$name] = $content;

                continue;
            }

            $legacyBodies[$index] = $content;
        }

        foreach ($legacyBodies as $index => $content) {
            $bodies['BD'.($index + 1)] ??= $content;
        }

        return $bodies;
    }

    private function canonicalBodyIdentifier(string $name): ?string
    {
        $normalized = strtoupper(html_entity_decode(trim($name), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $normalized = (string) preg_replace('/[^A-Z0-9]+/', '_', $normalized);
        $normalized = trim($normalized, '_');

        if (preg_match(self::BODY_ID_PATTERN, $normalized) || $this->isSubSlotId($normalized)) {
            return $normalized;
        }

        if (preg_match('/(?:^|_)(?<id>BD\d+(?:_[A-Z][A-Z0-9_]*)?)(?=_|$)/', $normalized, $matches)) {
            return $matches['id'];
        }

        return null;
    }

    private function decorateLegacyBdHeadings(string $html): string
    {
        return (string) preg_replace_callback(
            '/<h([1-3])\b([^>]*)>/i',
            function (array $matches) {
                $level = $matches[1];
                $attributes = $matches[2] ?? '';

                if (preg_match('/\bclass=(["\'])(.*?)\1/i', $attributes, $classMatch)) {
                    if (preg_match('/\blp-legacy-bd-heading\b/', $classMatch[2])) {
                        return '<h'.$level.$attributes.'>';
                    }

                    $updatedClass = 'class='.$classMatch[1].trim($classMatch[2].' lp-legacy-bd-heading').$classMatch[1];
                    $attributes = preg_replace('/\bclass=(["\']).*?\1/i', $updatedClass, $attributes, 1);

                    return '<h'.$level.$attributes.'>';
                }

                return '<h'.$level.$attributes.' class="lp-legacy-bd-heading">';
            },
            $html
        );
    }

    private function isSubSlotId(string $id): bool
    {
        return (bool) preg_match(self::SUB_SLOT_ID_PATTERN, $id);
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
        return (string) preg_replace_callback(
            '/\bsrc=(["\'])template_images\//i',
            fn (array $matches) => 'src='.$matches[1].'../../storage/templates/'.$template->uuid.'/images/'.$template->asset_unique_uuid.'-',
            $html
        );
    }

    private function rewriteAngleImagePaths(string $html, Angle $angle): string
    {
        return (string) preg_replace_callback(
            '/\bsrc=(["\'])angle_images\//i',
            fn (array $matches) => 'src='.$matches[1].'../../storage/angles/'.$angle->uuid.'/images/'.$angle->asset_unique_uuid.'-',
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
