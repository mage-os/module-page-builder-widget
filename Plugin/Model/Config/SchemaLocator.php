<?php
declare(strict_types=1);

namespace MageOS\PageBuilderWidget\Plugin\Model\Config;

use Magento\Framework\Config\SchemaLocatorInterface;
use Magento\Framework\Module\Dir\Reader;

class SchemaLocator
{
    /**
     * Path to corresponding XSD file with validation rules for merged config
     *
     * @var ?string
     */
    protected ?string $_schema = null;

    /**
     * Path to corresponding XSD file with validation rules for separate config files
     *
     * @var ?string
     */
    protected ?string $_perFileSchema = null;

    /**
     * @param Reader $moduleReader
     */
    public function __construct(
        Reader $moduleReader
    ) {
        $etcDir = $moduleReader->getModuleDir(\Magento\Framework\Module\Dir::MODULE_ETC_DIR, 'MageOS_PageBuilderWidget');
        $this->_schema = $etcDir . '/widget.xsd';
        $this->_perFileSchema = $etcDir . '/widget_file.xsd';
    }

    /**
     * Get path to merged config schema
     *
     * @param SchemaLocatorInterface $subject
     * @param string|null $result
     * @return string|null
     */
    public function afterGetSchema(
        SchemaLocatorInterface $subject,
        ?string $result
    ): ?string
    {
        return $this->_schema;
    }

    /**
     * Get path to per file validation schema
     *
     * @param SchemaLocatorInterface $subject
     * @param string|null $result
     * @return string|null
     */
    public function afterGetPerFileSchema(
        SchemaLocatorInterface $subject,
        ?string $result
    ): ?string
    {
        return $this->_perFileSchema;
    }
}
