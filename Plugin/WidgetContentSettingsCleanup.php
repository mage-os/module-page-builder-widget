<?php
declare(strict_types=1);

namespace MageOS\PageBuilderWidget\Plugin;

class WidgetContentSettingsCleanup
{
    /**
     * Get path to merged config schema
     *
     * @return string|null
     */
    public function afterGetContent(
        $subject,
        ?string $result
    ) {
        return preg_replace_callback(
            '/<div[^>]*data-content-type="widget"[^>]*>/i',
            function ($matches) {
                return preg_replace('/\s*content_settings="[^"]*"/i', '', $matches[0]);
            },
            $result
        );
    }
}
