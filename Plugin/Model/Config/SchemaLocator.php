<?php
declare(strict_types=1);

namespace MageOS\PageBuilderWidget\Plugin\Model\Config;

class SchemaLocator
{
    /**
     * Path to corresponding XSD file with validation rules for merged config
     *
     * @var string
     */
    protected $_schema = null;

    /**
     * Path to corresponding XSD file with validation rules for separate config files
     *
     * @var string
     */
    protected $_perFileSchema = null;

    /**
     * @param \Magento\Framework\Module\Dir\Reader $moduleReader
     */
    public function __construct(
        \Magento\Framework\Module\Dir\Reader $moduleReader
    ) {
        $etcDir = $moduleReader->getModuleDir(\Magento\Framework\Module\Dir::MODULE_ETC_DIR, 'MageOS_PageBuilderWidget');
        $this->_schema = $etcDir . '/widget.xsd';
        $this->_perFileSchema = $etcDir . '/widget_file.xsd';
    }

    /**
     * Get path to merged config schema
     *
     * @return string|null
     */
    public function afterGetSchema(
        \Magento\Framework\Config\SchemaLocatorInterface $subject,
        ?string $result
    ) {
        return $this->_schema;
    }

    /**
     * Get path to per file validation schema
     *
     * @return string|null
     */
    public function afterGetPerFileSchema(
        \Magento\Framework\Config\SchemaLocatorInterface $subject,
        ?string $result
    ) {
        return $this->_perFileSchema;
    }
}
