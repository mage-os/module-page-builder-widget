<?php
declare(strict_types=1);

namespace MageOS\PageBuilderWidget\Plugin;

use Magento\Cms\Api\Data\BlockInterface;

class WidgetContentSettingsCleanup
{
    /**
     * Get path to merged config schema
     * @param $subject
     * @param string|null $result
     * @return string|null
     */
    public function afterGetContent(
        $subject,
        ?string $result
    ): ?string
    {
        return preg_replace_callback(
            '/<div[^>]*data-content-type="widget"[^>]*>/i',
            function ($matches) {
                return preg_replace('/\s*content_settings="[^"]*"/i', '', $matches[0]);
            },
            $result
        );
    }
}
