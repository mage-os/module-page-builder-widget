<?php
declare(strict_types=1);

namespace MageOS\PageBuilderWidget\Block\Adminhtml\Widget\Preview;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\ViewModel\Product\OptionsData;
use Magento\CatalogWidget\Model\Rule;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Url\EncoderInterface;
use Magento\Framework\View\LayoutFactory;
use Magento\Rule\Model\Condition\Sql\Builder as SqlBuilder;
use Magento\Widget\Block\BlockInterface;
use Magento\Widget\Helper\Conditions;

class ProductsList extends \Magento\CatalogWidget\Block\Product\ProductsList implements BlockInterface, IdentityInterface
{

    /**
     * @var ReviewRenderer
     */
    protected $reviewRenderer;

    public function __construct(
        Context $context,
        CollectionFactory $productCollectionFactory,
        Visibility $catalogProductVisibility,
        HttpContext $httpContext,
        SqlBuilder $sqlBuilder,
        Rule $rule,
        Conditions $conditionsHelper,
        ReviewRenderer $reviewRenderer,
        array $data = [],
        ?Json $json = null,
        ?LayoutFactory $layoutFactory = null,
        ?EncoderInterface $urlEncoder = null,
        ?CategoryRepositoryInterface $categoryRepository = null,
        ?OptionsData $optionsData = null
    )
    {
        parent::__construct(
            $context,
            $productCollectionFactory,
            $catalogProductVisibility,
            $httpContext,
            $sqlBuilder,
            $rule,
            $conditionsHelper,
            $data,
            $json,
            $layoutFactory,
            $urlEncoder,
            $categoryRepository,
            $optionsData
        );
        $this->reviewRenderer = $reviewRenderer;
    }

    public function getCacheKeyInfo(): array
    {
        return [];
    }

    public function getCacheKey(): string
    {
        return '';
    }

    /**
     * Load block html from cache storage
     *
     * @return string
     */
    protected function _loadCache(): string
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

    /**
     * Render pagination HTML
     *
     * @return string
     * @throws LocalizedException
     */
    public function getPagerHtml()
    {
        if ($this->showPager() && $this->getProductCollection()->getSize() > $this->getProductsPerPage()) {
            if (!$this->pager) {
                $this->pager = $this->getLayout()->createBlock(
                    \Magento\Catalog\Block\Product\Widget\Html\Pager::class,
                    $this->getWidgetPagerBlockName()
                );

                $this->pager->setTemplate('MageOS_PageBuilderWidget::widget-preview/html/pager.phtml');

                $this->pager->setUseContainer(true)
                    ->setShowAmounts(true)
                    ->setShowPerPage(false)
                    ->setPageVarName($this->getData('page_var_name'))
                    ->setLimit($this->getProductsPerPage())
                    ->setTotalLimit($this->getProductsCount())
                    ->setCollection($this->getProductCollection());
            }
            if ($this->pager instanceof \Magento\Framework\View\Element\AbstractBlock) {
                return $this->pager->toHtml();
            }
        }
        return '';
    }

    /**
     * Get product reviews summary
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param bool $templateType
     * @param bool $displayIfNoReviews
     * @return string
     */
    public function getReviewsSummaryHtml(
        \Magento\Catalog\Model\Product $product,
        $templateType = false,
        $displayIfNoReviews = false
    ) {
        return $this->reviewRenderer->getReviewsSummaryHtml($product, $templateType, $displayIfNoReviews);
    }
}
