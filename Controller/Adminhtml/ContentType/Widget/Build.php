<?php
declare(strict_types=1);

namespace MageOS\PageBuilderWidget\Controller\Adminhtml\ContentType\Widget;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface as HttpPostActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\DataObject;
use Magento\Widget\Model\Widget;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\View\LayoutInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Escaper;

class Build extends \Magento\Backend\App\Action implements HttpPostActionInterface
{
    const ADMIN_RESOURCE = 'Magento_Widget::widget_instance';

    const SCRIPT_REPLACE_REGEX = '#<script\b[^>]*>.*?</script>#is';

    /**
     * @param Context $context
     * @param Widget $widget
     * @param JsonFactory $jsonFactory
     * @param LayoutInterface $layout
     * @param ObjectManagerInterface $objectManager
     * @param Escaper $escaper
     */
    public function __construct(
        protected Context $context,
        protected Widget $widget,
        protected JsonFactory $jsonFactory,
        protected LayoutInterface $layout,
        protected ObjectManagerInterface $objectManager,
        protected Escaper $escaper
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|Json|ResultInterface
     */
    public function execute()
    {
        $type = $this->getRequest()->getPost('widget_type');
        if (!$type
            || !class_exists($type)
            || !is_subclass_of($type, \Magento\Widget\Block\BlockInterface::class)
        ) {
            return $this->returnEmptyResult();
        }

        $params = $this->getRequest()->getPost('parameters', []);
        $widgetConfig = $this->widget->getConfigAsObject($type);
        $params['pagebuilder_widget_directive'] = true;
        $params = $this->sanitizeWidgetParams($params, $widgetConfig);

        $widgetDeclaration = $this->widget->getWidgetDeclaration($type, $params, true);
        $widgetData = $widgetDeclaration;
        $widgetTemplate = $params["template"] ?? $widgetConfig["parameters"]["template"]["value"];

        if (
            !$widgetConfig->getType()
        ) {
            return $this->returnEmptyResult();
        }

        try {
            if ($widgetConfig->getData('previewBlockArguments')) {
                foreach ($widgetConfig->getData('previewBlockArguments') as $key => $argument) {
                    if ($argument["type"] === "object") {
                        $allowedObject = $argument['value'];
                        if (class_exists($allowedObject) || interface_exists($allowedObject)) {
                            $params[$key] = $this->objectManager->get($argument["value"]);
                        } else {
                            $params[$key] = null;
                        }
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
            return $this->returnEmptyResult();
        }


        $result = $this->jsonFactory->create();
        $result->setData([
            'widgetDeclaration' => $widgetDeclaration,
            'widgetPreview' => $widgetPreview,
            'widgetData' => $widgetData
        ]);
        return $result;
    }

    /**
     * @return Json
     */
    private function returnEmptyResult(): Json
    {
        $result = $this->jsonFactory->create();
        $result->setData([
            'widgetDeclaration' => null,
            'widgetPreview' => null,
            'widgetData' => null
        ]);
        return $result;
    }

    /**
     * @param array $params
     * @param DataObject $widgetConfig
     * @return array
     */
    public function sanitizeWidgetParams(array $params, DataObject $widgetConfig): array
    {
        $paramsConfig = $widgetConfig->getData('parameters');
        $paramKeys = array_keys($paramsConfig);
        foreach ($params as $key => $value) {
            if ($key === "pagebuilder_widget_directive") {
                continue;
            }
            if (!in_array($key, $paramKeys)) {
                unset($params[$key]);
            }
            $paramsConfigKey = $key;
            if (!isset($paramsConfig[$key]) && $key === 'conditions') {
                $paramsConfigKey = 'condition';
            }
            switch ($paramsConfig[$paramsConfigKey]->getType()) {
                case 'select':
                    $valueFound = false;
                    foreach ($paramsConfig[$paramsConfigKey]['values'] as $valueKey => $valueData) {
                        if ($value === $valueData['value']) {
                            $valueFound = true;
                            break;
                        }
                    }
                    if (!$valueFound) {
                        if (isset($paramsConfig[$paramsConfigKey]['source_model'])) {
                            $paramsConfig[$paramsConfigKey]['values'] = $this->objectManager->get(
                                $paramsConfig[$paramsConfigKey]['source_model']
                            )->toOptionArray();
                        }
                        $params[$key] = $paramsConfig[$paramsConfigKey]['values'][0]['value'];
                    }
                    break;
                case 'text':
                    $value = preg_replace(self::SCRIPT_REPLACE_REGEX, '', $value);
                    $params[$key] = $this->escaper->escapeHtml($value);
                    break;
                default:
                    if (is_array($value)) {
                        foreach ($value as $repeatableItemKey => $repeatableItemData) {
                            foreach ($repeatableItemData as $repeatableItemDataKey => $repeatableItemDataValue) {
                                $repeatableItemDataValue = preg_replace(self::SCRIPT_REPLACE_REGEX, '', $repeatableItemDataValue);
                                $repeatableItemData[$repeatableItemDataKey] = $this->escaper->escapeHtml($repeatableItemDataValue);
                            }
                            $value[$repeatableItemKey] = $repeatableItemData;
                        }
                        $params[$key] = $value;
                    } else {
                        $value = preg_replace(self::SCRIPT_REPLACE_REGEX, '', $value);
                        $params[$key] = $this->escaper->escapeHtml($value);
                    }
                    break;
            }
        }
        return $params;
    }
}