<?php

namespace App\Services;

use App\Models\ApiCategory;
use App\Models\UserApiInstance;

class ApiExportService
{
    /** Platform file name from category name (e.g. "Novelix" -> novelix.php). */
    public function getPlatformFileName(ApiCategory $category): string
    {
        $base = strtolower(preg_replace('/\s+/', '_', $category->name));

        return $base . '.php';
    }

    /**
     * Get field name => placeholder string for a category's platform file.
     *
     * @return array<string, string>
     */
    public function getVariableMapping(ApiCategory $category): array
    {
        $filename = $this->getPlatformFileName($category);

        return $this->getPlaceholderMap($filename);
    }

    /**
     * Build search => replace pairs for credential injection.
     *
     * @return array<string, string>
     */
    public function buildReplacementMap(UserApiInstance $instance): array
    {
        $category = $instance->category;
        if (!$category) {
            return [];
        }
        $mapping = $this->getVariableMapping($category);
        $credentials = $instance->credentials;
        $pairs = [];
        foreach ($mapping as $fieldName => $search) {
            $value = $credentials[$fieldName] ?? '';
            $pairs[$search] = $this->buildReplacement($search, $value);
        }

        return $pairs;
    }

    /**
     * Inject instance credentials into file content (placeholders replaced).
     */
    public function injectCredentials(string $content, string $filename, ?UserApiInstance $instance): string
    {
        if (!$instance) {
            return $content;
        }
        $category = $instance->category;
        if (!$category) {
            return $content;
        }
        // Use platform filename derived from category, not the passed filename
        // This ensures we use the correct placeholder map for platform-based files
        $platformFileName = $this->getPlatformFileName($category);
        $map = $this->getPlaceholderMap($platformFileName);
        if ($map === []) {
            return $content;
        }
        $replacements = $this->buildReplacementMap($instance);
        foreach ($replacements as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }

        return $content;
    }

    /**
     * Field name => placeholder string per platform file.
     *
     * @return array<string, string>
     */
    private function getPlaceholderMap(string $filename): array
    {
        $maps = [
            'aweber.php' => [
                'client_id' => '$clientId = "";',
                'client_secret' => '$clientSecret = "";',
                'account_id' => '$accountId = "";',
                'list_id' => '$listId = "";',
            ],
            'electra.php' => ['affid' => "'affid' => '13',"],
            'dark.php' => [
                'ai' => "'ai' => '',",
                'ci' => "'ci' => '',",
                'gi' => "'gi' => '',",
                'username' => '$username = "";',
                'password' => '$password = "";',
                'api_key' => '$xapikey = "";',
            ],
            'trackbox.php' => [
                'endpoint_url' => '$endpoint = "";',
                'ai' => "'ai' => '',",
                'ci' => "'ci' => '',",
                'gi' => "'gi' => '',",
                'username' => '$username = "";',
                'password' => '$password = "";',
                'api_key' => '$xapikey = "";',
            ],
            'elps.php' => [
                'ai' => "'ai' => '',",
                'ci' => "'ci' => '',",
                'gi' => "'gi' => '',",
                'username' => '$username = "";',
                'password' => '$password = "";',
                'api_key' => '$xapikey = "";',
            ],
            'meeseeksmedia.php' => ['api_key' => '$xapikey = "";'],
            'novelix.php' => [
                'affid' => "'affid' => '',",
                'api_key' => '$xapikey = "";',
            ],
            'tigloo.php' => [
                'ai' => "'ai' => '',",
                'ci' => "'ci' => '',",
                'gi' => "'gi' => '',",
                'username' => '$username = "";',
                'password' => '$password = "";',
                'api_key' => '$xapikey = "";',
            ],
            'koi.php' => ['api_key' => '$xapikey = "";'],
            'pastile.php' => [
                'ai' => "'ai' => '',",
                'ci' => "'ci' => '',",
                'gi' => "'gi' => '',",
                'username' => '$username = "";',
                'password' => '$password = "";',
                'api_key' => '$xapikey = "";',
            ],
            'riceleads.php' => ['affid' => "'affid' => '',"],
            'newmedis.php' => [
                'ai' => "'ai' => '',",
                'ci' => "'ci' => '',",
                'gi' => "'gi' => '',",
                'username' => '$username = "";',
                'password' => '$password = "";',
                'api_key' => '$xapikey = "";',
            ],
            'seamediaone.php' => [
                'ai' => "'ai' => '',",
                'ci' => "'ci' => '',",
                'gi' => "'gi' => '',",
                'username' => '$username = "";',
                'password' => '$password = "";',
                'api_key' => '$xapikey = "";',
            ],
            'nauta.php' => ['api_token' => '$nautaApiToken = "";'],
            'irev.php' => [
                'endpoint_url' => '$endpoint = "";',
                'api_token' => '$irevApiToken = "";',
            ],
            'magicads.php' => [
                'ai' => "'ai' => '',",
                'ci' => "'ci' => '',",
                'gi' => "'gi' => '',",
                'username' => '$username = "";',
                'password' => '$password = "";',
                'api_key' => '$xapikey = "";',
            ],
            'adzentric.php' => [
                'affid' => "'affid' => '',",
                'api_key' => '$xapikey = "";',
            ],
        ];

        return $maps[$filename] ?? [];
    }

    private function buildReplacement(string $search, string $value): string
    {
        $escaped = addslashes($value);
        if (strpos($search, '""') !== false) {
            return str_replace('""', '"' . $escaped . '"', $search);
        }
        if (strpos($search, "''") !== false) {
            return str_replace("''", "'" . $escaped . "'", $search);
        }
        if (preg_match("/^(.+)'([^']*)'(.*)$/s", $search, $m)) {
            return $m[1] . "'" . $escaped . "'" . $m[3];
        }

        return $search;
    }
}
