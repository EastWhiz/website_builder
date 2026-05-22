<?php

namespace App\Services;

use App\Models\Angle;
use App\Models\Template;

class AngleTemplateMergeService
{
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
            $mainJs .= $item->content . "\n";
        });

        foreach ($allBodies as $key => $body) {
            $bodyKey = $key + 1;
            $mainHtml = str_replace("<!--INTERNAL--BD$bodyKey--EXTERNAL-->", $body->content, $mainHtml);
        }

        $mainHtml = preg_replace(
            '/src="angle_images\//',
            'src="../../storage/angles/' . $angle->uuid . '/images/' . $angle->asset_unique_uuid . '-',
            $mainHtml
        );

        $mainHtml = preg_replace(
            '/src="template_images\//',
            'src="../../storage/templates/' . $template->uuid . '/images/' . $template->asset_unique_uuid . '-',
            $mainHtml
        );

        $mainCss = preg_replace(
            '/fonts\//',
            '../../storage/templates/' . $template->uuid . '/fonts/' . $template->asset_unique_uuid . '-',
            $mainCss
        );

        return [
            'main_html' => $mainHtml,
            'main_css' => $mainCss,
            'main_js' => $mainJs,
        ];
    }
}
