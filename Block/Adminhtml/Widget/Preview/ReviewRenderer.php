<?php
declare(strict_types=1);

namespace MageOS\PageBuilderWidget\Block\Adminhtml\Widget\Preview;

use Magento\Catalog\Block\Product\ReviewRendererInterface;

/**
 * Review renderer
 */
class ReviewRenderer extends \Magento\Review\Block\Product\ReviewRenderer implements ReviewRendererInterface
{
    /**
     * Array of available template name
     *
     * @var array
     */
    protected $_availableTemplates = [
        self::FULL_VIEW => 'MageOS_PageBuilderWidget::widget-preview/review/helper/summary.phtml',
        self::SHORT_VIEW => 'MageOS_PageBuilderWidget::widget-preview/review/helper/summary_short.phtml',
    ];
}
