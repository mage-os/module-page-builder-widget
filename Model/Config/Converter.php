<?php
declare(strict_types=1);

namespace MageOS\PageBuilderWidget\Model\Config;

class Converter extends \Magento\Widget\Model\Config\Converter implements \Magento\Framework\Config\ConverterInterface
{
    /**
     * @inheritdoc
     *
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * phpcs:disable Generic.Metrics.NestingLevel
     */
    public function convert($source): array
    {
        $widgets = [];
        $xpath = new \DOMXPath($source);
        /** @var $widget \DOMNode */
        foreach ($xpath->query('/widgets/widget') as $widget) {
            $widgetAttributes = $widget->attributes;
            $widgetArray = ['@' => []];
            $widgetArray['@']['type'] = $widgetAttributes->getNamedItem('class')->nodeValue;

            $isEmailCompatible = $widgetAttributes->getNamedItem('is_email_compatible');
            if ($isEmailCompatible !== null) {
                $widgetArray['is_email_compatible'] = $isEmailCompatible->nodeValue == 'true' ? '1' : '0';
            }
            $placeholderImage = $widgetAttributes->getNamedItem('placeholder_image');
            if ($placeholderImage !== null) {
                $widgetArray['placeholder_image'] = $placeholderImage->nodeValue;
            }

            $widgetId = $widgetAttributes->getNamedItem('id');
            /** @var $widgetSubNode \DOMNode */
            foreach ($widget->childNodes as $widgetSubNode) {
                switch ($widgetSubNode->nodeName) {
                    case 'label':
                        $widgetArray['name'] = $widgetSubNode->nodeValue;
                        break;
                    case 'description':
                        $widgetArray['description'] = $widgetSubNode->nodeValue;
                        break;
                    case 'parameters':
                        /** @var $parameter \DOMNode */
                        foreach ($widgetSubNode->childNodes as $parameter) {
                            if ($parameter->nodeName === '#text' || $parameter->nodeName === '#comment') {
                                continue;
                            }
                            $subNodeAttributes = $parameter->attributes;
                            $parameterName = $subNodeAttributes->getNamedItem('name')->nodeValue;
                            $widgetArray['parameters'][$parameterName] = $this->_convertParameter($parameter);
                        }
                        break;
                    case 'containers':
                        if (!isset($widgetArray['supported_containers'])) {
                            $widgetArray['supported_containers'] = [];
                        }
                        foreach ($widgetSubNode->childNodes as $container) {
                            if ($container->nodeName === '#text' || $container->nodeName === '#comment') {
                                continue;
                            }
                            // phpcs:ignore Magento2.Performance.ForeachArrayMerge
                            $widgetArray['supported_containers'] = array_merge(
                                $widgetArray['supported_containers'],
                                $this->_convertContainer($container)
                            );
                        }
                        break;
                    case 'previewTemplates':
                        /** @var $previewTemplate \DOMNode */
                        foreach ($widgetSubNode->childNodes as $previewTemplate) {
                            if ($previewTemplate->nodeName === '#text') {
                                continue;
                            }
                            $subNodeAttributes = $previewTemplate->attributes;
                            $parameterName = $subNodeAttributes->getNamedItem('name')->nodeValue;
                            $widgetArray['previewTemplates'][$parameterName] = $previewTemplate->nodeValue;
                        }
                        break;
                    case 'previewBlockArguments':
                        /** @var $argument \DOMNode */
                        foreach ($widgetSubNode->childNodes as $argument) {
                            if ($argument->nodeName === '#text') {
                                continue;
                            }
                            $subNodeAttributes = $argument->attributes;
                            $parameterType = $subNodeAttributes->getNamedItem('type')->nodeValue;
                            $parameterName = $subNodeAttributes->getNamedItem('name')->nodeValue;
                            $widgetArray['previewBlockArguments'][$parameterName] = [
                                'type' => $parameterType,
                                'value' => $argument->nodeValue
                            ];
                        }
                        break;
                    case 'previewBlock':
                        $widgetArray['previewBlock'] = $widgetSubNode->nodeValue;
                        break;
                    case 'previewCss':
                        $widgetArray['previewCss'] = $widgetSubNode->nodeValue;
                        break;
                    case 'previewJs':
                        $widgetArray['previewJs'] = $widgetSubNode->nodeValue;
                        break;
                    case '#comment':
                    case "#text":
                        break;
                    default:
                        throw new \LogicException(
                            sprintf(
                                "Unsupported child xml node '%s' found in the 'widget' node",
                                $widgetSubNode->nodeName
                            )
                        );
                }
            }
            $widgets[$widgetId->nodeValue] = $widgetArray;
        }
        return $widgets;
    }
}
