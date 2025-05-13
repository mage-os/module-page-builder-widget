<?php
declare(strict_types=1);

namespace MageOS\PageBuilderWidget\Component\Form;

use Magento\Backend\Model\UrlInterface as BackendUrlInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Framework\View\Element\UiComponent\ContextInterface;

class WidgetData extends \Magento\Ui\Component\Form\Field
{
    const HTML_ID_PLACEHOLDER = 'HTML_ID_PLACEHOLDER';

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        protected BackendUrlInterface $backendUrl,
        $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function prepare()
    {
        $config = $this->getData('config');
        $config['widgetUrl'] = $this->backendUrl->getUrl(
            'adminhtml/widget/index',
            [
                'widget_target_id' => self::HTML_ID_PLACEHOLDER
            ]
        );
        $this->setData('config', $config);
        parent::prepare();
    }
}
