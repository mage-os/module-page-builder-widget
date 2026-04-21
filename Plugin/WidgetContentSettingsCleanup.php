<?php
declare(strict_types=1);

namespace MageOS\PageBuilderWidget\Plugin;

class WidgetContentSettingsCleanup
{
    /**
     * @param mixed $subject
     * @param string|null $result
     * @return string|null
     */
    public function afterGetContent(
        $subject,
        ?string $result
    ): ?string {
        if ($result === null || $result === '') {
            return $result;
        }

        return preg_replace_callback(
            '/<div[^>]*data-content-type="widget"[^>]*>/i',
            static fn(array $matches): string => (string)preg_replace(
                '/\s*content_settings="[^"]*"/i',
                '',
                $matches[0]
            ),
            $result
        );
    }
}
