<?php
declare(strict_types=1);

namespace MageOS\PageBuilderWidget\Model\Config\ContentType\AdditionalData\Provider;

use Magento\Framework\UrlInterface;
use Magento\PageBuilder\Model\Config\ContentType\AdditionalData\ProviderInterface;

class WidgetFormUrl implements ProviderInterface
{

    /**
     * BlockDataUrl constructor.
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        protected UrlInterface $urlBuilder
    )
    {
    }

    /**
     * @inheritdoc
     */
    public function getData(string $itemName) : array
    {
        return [$itemName => $this->urlBuilder->getUrl('admin/widget/loadOptions')];
    }
}
