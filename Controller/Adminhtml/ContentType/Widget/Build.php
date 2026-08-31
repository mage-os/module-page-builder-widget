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
use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;
use MageOS\PageBuilderWidget\Block\Adminhtml\Widget\PreviewAssets;
use Magento\Framework\App\ObjectManager;
use Psr\Log\LoggerInterface;

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
    private LoggerInterface $logger;

    public function __construct(
        protected Context $context,
        protected Widget $widget,
        protected JsonFactory $jsonFactory,
        protected LayoutInterface $layout,
        protected ObjectManagerInterface $objectManager,
        protected ConfigInterface $objectManagerConfig,
        protected Escaper $escaper,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($context);
        $this->logger = $logger ?? ObjectManager::getInstance()->get(LoggerInterface::class);
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
        $templateParam = $widgetConfig["parameters"]["template"] ?? null;
        $widgetTemplate = $params["template"] ?? ($templateParam["value"] ?? null);

        if (
            !$widgetConfig->getType()
        ) {
            return $this->returnEmptyResult();
        }

        $widgetPreview = null;

        try {
            $params = $this->applyPreviewBlockArguments($widgetConfig, $params);
            $blockType = (string)($widgetConfig->getData('previewBlock') ?: $type);
            $widgetBlock = $this->layout->createBlock($blockType, 'widgetPreview', ['data' => $params]);
            $this->applyPreviewTemplate($widgetConfig, $widgetBlock, $blockType, $widgetTemplate);
            $this->addPreviewAssets($widgetConfig, $widgetBlock);

            if ($this->isTemplateRenderable($widgetBlock)) {
                $widgetPreview = $widgetBlock->toHtml();
            }
        } catch (\Throwable $e) {
            $widgetPreview = null;
            $this->logger->warning(
                'MageOS_PageBuilderWidget: unable to render the preview of widget ' . $type
                . ': ' . $e->getMessage()
            );
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
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function applyPreviewBlockArguments(DataObject $widgetConfig, array $params): array
    {
        foreach ((array)$widgetConfig->getData('previewBlockArguments') as $key => $argument) {
            if (!is_array($argument) || !array_key_exists('value', $argument)) {
                continue;
            }
            if (($argument['type'] ?? '') !== 'object') {
                $params[$key] = $argument['value'];
                continue;
            }
            $allowedObject = $argument['value'];
            $params[$key] = class_exists($allowedObject) || interface_exists($allowedObject)
                ? $this->objectManager->get($allowedObject)
                : null;
        }

        return $params;
    }

    /**
     * Point the block at a preview template, if there is one that resolves
     */
    private function applyPreviewTemplate(
        DataObject $widgetConfig,
        BlockInterface $widgetBlock,
        string $blockType,
        ?string $widgetTemplate
    ): void {
        foreach ((array)$widgetConfig->getData('previewTemplates') as $mainTemplate => $previewTemplate) {
            if ($mainTemplate === $widgetTemplate) {
                $widgetBlock->setTemplate($previewTemplate);

                return;
            }
        }

        if ($widgetTemplate === null || $widgetTemplate === '' || !$widgetBlock instanceof Template) {
            return;
        }

        $typePath = explode('\\', $blockType);
        if (!isset($typePath[1])) {
            return;
        }

        $moduleTemplate = $typePath[0] . '_' . $typePath[1] . '::widget-preview/' . $widgetTemplate;
        if ($widgetBlock->getTemplateFile($moduleTemplate)) {
            $widgetBlock->setTemplate($moduleTemplate);
        }
    }

    private function addPreviewAssets(DataObject $widgetConfig, BlockInterface $widgetBlock): void
    {
        $previewData = ['template' => 'MageOS_PageBuilderWidget::preview-assets.phtml'];
        if ($widgetConfig->getData('previewCss')) {
            $previewData['css'] = $widgetConfig->getData('previewCss');
        }
        if ($widgetConfig->getData('previewJs')) {
            $previewData['js'] = $widgetConfig->getData('previewJs');
        }
        $widgetBlock->addChild('previewAssets', PreviewAssets::class, $previewData);
    }

    /**
     * Whether the block has a template that resolves in the area it is rendered in
     */
    private function isTemplateRenderable(BlockInterface $widgetBlock): bool
    {
        if (!$widgetBlock instanceof Template) {
            return true;
        }

        return (string)$widgetBlock->getTemplate() !== '' && (bool)$widgetBlock->getTemplateFile();
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
