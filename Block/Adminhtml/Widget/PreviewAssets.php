<?php
declare(strict_types=1);

namespace MageOS\PageBuilderWidget\Block\Adminhtml\Widget;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;
use Magento\Framework\View\Asset\Repository;

/**
 * Class Accordion
 * @package Dadolun\RepeatableWidget\Block\Widget
 */
class PreviewAssets extends Template implements BlockInterface
{


    public function __construct(
        protected Repository $assetRepository,
        Template\Context     $context,
        array                $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @param $asset
     * @return string
     * @throws LocalizedException
     */
    public function getAssetUrl($asset): string
    {
        return $this->assetRepository->createAsset($asset)->getUrl();
    }
}
