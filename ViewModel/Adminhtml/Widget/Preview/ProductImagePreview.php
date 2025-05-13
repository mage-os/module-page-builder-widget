<?php
declare(strict_types=1);

namespace MageOS\PageBuilderWidget\ViewModel\Adminhtml\Widget\Preview;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Helper\Image as ImageHelper;

class ProductImagePreview
{
    public function __construct(
        protected ImageHelper $imageHelper
    ) {
    }

    /**
     * @param Product $product
     * @return string
     */
    public function getProductImage($product)
    {
        return $this->imageHelper->init($product, 'product_listing_thumbnail_preview')->getUrl();
    }
}
