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
use Magento\Framework\ObjectManager\ConfigInterface;
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
        protected ConfigInterface $objectManagerConfig,
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
        if (!$this->isTypeValid($type)) {
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
        $paramsConfig = $widgetConfig->getData('parameters') ?? [];
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
            if (!isset($paramsConfig[$paramsConfigKey])) {
                unset($params[$key]);
                continue;
            }
            switch ($paramsConfig[$paramsConfigKey]->getType()) {
                case 'select':
                    $options = $this->getSelectOptions($paramsConfig[$paramsConfigKey]);
                    if ($options !== [] && !$this->isAllowedSelectValue($value, $options)) {
                        $params[$key] = $options[0]['value'];
                    }
                    break;
                case 'text':
                    $params[$key] = $this->sanitizeStringValue($value);
                    break;
                default:
                    if (is_array($value)) {
                        foreach ($value as $repeatableItemKey => $repeatableItemData) {
                            if (is_array($repeatableItemData)) {
                                foreach ($repeatableItemData as $repeatableItemDataKey => $repeatableItemDataValue) {
                                    $repeatableItemData[$repeatableItemDataKey] = $this->sanitizeStringValue($repeatableItemDataValue);
                                }
                            }
                            $value[$repeatableItemKey] = $repeatableItemData;
                        }
                        $params[$key] = $value;
                    } else {
                        $params[$key] = $this->sanitizeStringValue($value);
                    }
                    break;
            }
        }
        return $params;
    }

    /**
     * Collect the option values a select parameter allows
     *
     * @return list<array<string, mixed>>
     */
    private function getSelectOptions(DataObject $paramConfig): array
    {
        $values = $paramConfig->getData('values');
        if (!is_array($values) || $values === []) {
            $sourceModel = $paramConfig->getData('source_model');
            $values = $sourceModel ? $this->objectManager->get($sourceModel)->toOptionArray() : [];
        }

        return $this->flattenSelectOptions(is_array($values) ? $values : []);
    }

    /**
     * Flatten option groups, so that a grouped source model cannot yield an array as fallback value
     *
     * @param array<mixed> $values
     * @return list<array<string, mixed>>
     */
    private function flattenSelectOptions(array $values): array
    {
        $options = [];
        foreach ($values as $option) {
            if (!is_array($option) || !array_key_exists('value', $option)) {
                continue;
            }
            if (is_array($option['value'])) {
                // phpcs:ignore Magento2.Performance.ForeachArrayMerge
                $options = array_merge($options, $this->flattenSelectOptions($option['value']));
                continue;
            }
            $options[] = $option;
        }

        return $options;
    }

    /**
     * Whether the posted value is one of the allowed option values
     *
     * @param list<array<string, mixed>> $options
     */
    private function isAllowedSelectValue(mixed $value, array $options): bool
    {
        if (!is_scalar($value)) {
            return false;
        }
        foreach ($options as $option) {
            if ((string)$option['value'] === (string)$value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $type
     * @return bool
     */
    public function isTypeValid($type): bool
    {
        if (!is_string($type) || $type === '') {
            return false;
        }

        $classToValidate = $type;
        if (!class_exists($type)) {
            $classToValidate = $this->objectManagerConfig->getInstanceType($type);
        }

        return is_string($classToValidate)
            && class_exists($classToValidate)
            && is_subclass_of($classToValidate, \Magento\Widget\Block\BlockInterface::class);
    }

    /**
     * @param mixed $value
     * @return string
     */
    protected function sanitizeStringValue($value): string
    {
        if (!is_string($value)) {
            return '';
        }
        $stripped = preg_replace(self::SCRIPT_REPLACE_REGEX, '', $value);

        return $this->escaper->escapeHtml($stripped ?? '');
    }
}
