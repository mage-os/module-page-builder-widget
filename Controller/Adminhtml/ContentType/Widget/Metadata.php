<?php
declare(strict_types=1);

namespace MageOS\PageBuilderWidget\Controller\Adminhtml\ContentType\Widget;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Registry;
use Magento\Widget\Model\WidgetFactory;

class Metadata extends \Magento\Backend\App\AbstractAction
{
    /**
     * {@inheritdoc}
     */
    const ADMIN_RESOURCE = 'Magento_Widget::widget_instance';

    /**
     * @param WidgetFactory $widgetFactory
     * @param Registry $registry
     * @param Context $context
     */
    public function __construct(
        protected WidgetFactory $widgetFactory,
        protected Registry $registry,
        Context $context
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $widgetChooserConfig = $this->getAvailableWidgets();
        return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData($widgetChooserConfig);
    }

    /**
     * Return array of available widgets based on configuration
     *
     * @return array
     */
    protected function getAvailableWidgets(): array
    {
        $result = [];
        $allWidgets = $this->widgetFactory->create()->getWidgetsArray();
        $skipped = $this->getSkippedWidgets();
        foreach ($allWidgets as $widget) {
            if (is_array($skipped) && in_array($widget['type'], $skipped)) {
                continue;
            }
            $result[] = $widget;
        }
        array_unshift($result, [
            'type' => '',
            'code' => '',
            'name' => __('-- Please Select --'),
            'description' => ''
        ]);

        return $result;
    }

    /**
     * Return array of widgets disabled for selection
     *
     * @return ?array
     */
    protected function getSkippedWidgets(): ?array
    {
        return $this->registry->registry('skip_widgets');
    }
}
