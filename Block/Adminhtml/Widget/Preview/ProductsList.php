<?php
declare(strict_types=1);

namespace MageOS\PageBuilderWidget\Block\Adminhtml\Widget\Preview;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Widget\Block\BlockInterface;

/**
 * Catalog Products List widget block
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
 */
class ProductsList extends \Magento\CatalogWidget\Block\Product\ProductsList implements BlockInterface, IdentityInterface
{

    public function getCacheKeyInfo()
    {
        return [];
    }

    public function getCacheKey()
    {
        return [];
    }

    /**
     * Load block html from cache storage
     *
     * @return string
     */
    protected function _loadCache()
    {
        $collectAction = function () {
            if ($this->hasData('translate_inline')) {
                $this->inlineTranslation->suspend($this->getData('translate_inline'));
            }

            $this->_beforeToHtml();
            return $this->_toHtml();
        };

        return $collectAction();
    }
}
