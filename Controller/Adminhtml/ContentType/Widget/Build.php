<?php
declare(strict_types=1);

namespace MageOS\PageBuilderWidget\Controller\Adminhtml\ContentType\Widget;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface as HttpPostActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultInterface;
use Magento\Widget\Model\Widget;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\View\LayoutInterface;
use Magento\Framework\ObjectManagerInterface;

class Build extends \Magento\Backend\App\Action implements HttpPostActionInterface
{
    const ADMIN_RESOURCE = 'Magento_Widget::widget_instance';

    /**
     * @param Context $context
     * @param Widget $widget
     * @param JsonFactory $jsonFactory
     * @param LayoutInterface $layout
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        protected Context $context,
        protected Widget $widget,
        protected JsonFactory $jsonFactory,
        protected LayoutInterface $layout,
        protected ObjectManagerInterface $objectManager
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|Json|ResultInterface
     */
    public function execute()
    {
        $type = $this->getRequest()->getPost('widget_type');
        $params = $this->getRequest()->getPost('parameters', []);

        $widgetDeclaration = $this->widget->getWidgetDeclaration($type, $params, true);
        $params['pagebuilder_widget_directive'] = true;
        $widgetData = $this->widget->getWidgetDeclaration($type, $params, true);
        $widgetConfig = $this->widget->getConfigAsObject($type);
        $widgetTemplate = isset($params["template"]) ? $params["template"] : $widgetConfig["parameters"]["template"]["value"];

        try {
            if ($widgetConfig->getData('previewBlockArguments')) {
                foreach ($widgetConfig->getData('previewBlockArguments') as $key => $argument) {
                    if ($argument["type"] === "object") {
                        $params[$key] = $this->objectManager->get($argument["value"]);
                    } else {
                        $params[$key] = $argument["value"];
                    }
                }
            }
            if ($widgetConfig->getData('previewBlock')) {
                $type = $widgetConfig->getData('previewBlock');
            }
            $widgetBlock = $this->layout->createBlock($type, 'widgetPreview', ['data' => $params]);
            if ($widgetConfig->getData('previewTemplates')) {
                foreach ($widgetConfig->getData('previewTemplates') as $mainTemplate => $previewTemplate) {
                    if ($mainTemplate === $widgetTemplate) {
                        $widgetBlock->setTemplate($previewTemplate);
                    }
                }
            } else {
                $typePath = explode('\\', $type);
                $moduleTemplate = $typePath[0] . '_' . $typePath[1] . '::widget-preview/' . $widgetConfig["parameters"]["template"]["value"];
                $widgetBlock->setTemplate($moduleTemplate);
            }

            $previewData = [
                'template' => 'MageOS_PageBuilderWidget::preview-assets.phtml'
            ];
            if ($widgetConfig->getData('previewCss')) {
                $previewData['css'] = $widgetConfig->getData('previewCss');
            }
            if ($widgetConfig->getData('previewJs')) {
                $previewData['js'] = $widgetConfig->getData('previewJs');
            }
            $widgetBlock->addChild(
                'previewAssets',
                \MageOS\PageBuilderWidget\Block\Adminhtml\Widget\PreviewAssets::class,
                $previewData
            );
            $widgetPreview = $widgetBlock->toHtml();
        } catch (\Exception $e) {
            $widgetPreview = false;
        }


        $result = $this->jsonFactory->create();
        $result->setData([
            'widgetDeclaration' => $widgetDeclaration,
            'widgetPreview' => $widgetPreview,
            'widgetData' => $widgetData
        ]);
        return $result;
    }
}
