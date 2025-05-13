<?php
declare(strict_types=1);

namespace MageOS\PageBuilderWidget\Controller\Adminhtml\ContentType\Widget;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;

class Metadata extends \Magento\Backend\App\AbstractAction
{
    /**
     * {@inheritdoc}
     */
    const ADMIN_RESOURCE = 'Magento_Widget::widget_instance';

    /**
     * @param Context $context
     */
    public function __construct(
        protected \Magento\Widget\Model\WidgetFactory $widgetFactory,
        protected \Magento\Framework\Registry $registry,
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
    protected function getAvailableWidgets()
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
     * @return string[]
     */
    protected function getSkippedWidgets()
    {
        return $this->registry->registry('skip_widgets');
    }
}
