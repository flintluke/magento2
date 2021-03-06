<?php
/**
 * @copyright Copyright (c) 2014 X.commerce, Inc. (http://www.magentocommerce.com)
 */
namespace Magento\Eav\Model\Resource\Entity\Attribute;

use Magento\Eav\Model\Entity\Type;

/**
 * EAV attribute resource collection
 *
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Collection extends \Magento\Framework\Model\Resource\Db\Collection\AbstractCollection
{
    /**
     * Add attribute set info flag
     *
     * @var bool
     */
    protected $_addSetInfoFlag = false;

    /**
     * @var \Magento\Eav\Model\Config
     */
    protected $eavConfig;

    /**
     * @param \Magento\Framework\Data\Collection\EntityFactoryInterface $entityFactory
     * @param \Magento\Framework\Logger $logger
     * @param \Magento\Framework\Data\Collection\Db\FetchStrategyInterface $fetchStrategy
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\Eav\Model\Config $eavConfig
     * @param \Zend_Db_Adapter_Abstract|null $connection
     * @param \Magento\Framework\Model\Resource\Db\AbstractDb $resource
     */
    public function __construct(
        \Magento\Framework\Data\Collection\EntityFactoryInterface $entityFactory,
        \Magento\Framework\Logger $logger,
        \Magento\Framework\Data\Collection\Db\FetchStrategyInterface $fetchStrategy,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Eav\Model\Config $eavConfig,
        $connection = null,
        \Magento\Framework\Model\Resource\Db\AbstractDb $resource = null
    ) {
        $this->eavConfig = $eavConfig;
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $connection, $resource);
    }

    /**
     * Resource model initialization
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('Magento\Eav\Model\Entity\Attribute', 'Magento\Eav\Model\Resource\Entity\Attribute');
    }

    /**
     * Return array of fields to load attribute values
     *
     * @return string[]
     */
    protected function _getLoadDataFields()
    {
        return [
            'attribute_id',
            'entity_type_id',
            'attribute_code',
            'attribute_model',
            'backend_model',
            'backend_type',
            'backend_table',
            'frontend_input',
            'source_model'
        ];
    }

    /**
     * Specify select columns which are used for load arrtibute values
     *
     * @return $this
     */
    public function useLoadDataFields()
    {
        $this->getSelect()->reset(\Zend_Db_Select::COLUMNS);
        $this->getSelect()->columns($this->_getLoadDataFields());

        return $this;
    }

    /**
     * Specify attribute entity type filter
     *
     * @param  Type|int $type
     * @return $this
     */
    public function setEntityTypeFilter($type)
    {
        if ($type instanceof Type) {
            $additionalTable = $type->getAdditionalAttributeTable();
            $id = $type->getId();
        } else {
            $additionalTable = $this->getResource()->getAdditionalAttributeTable($type);
            $id = $type;
        }
        $this->addFieldToFilter('main_table.entity_type_id', $id);
        if ($additionalTable) {
            $this->join(
                ['additional_table' => $additionalTable],
                'additional_table.attribute_id = main_table.attribute_id'
            );
        }

        return $this;
    }

    /**
     * Specify attribute set filter
     *
     * @param int $setId
     * @return $this
     */
    public function setAttributeSetFilter($setId)
    {
        if (is_array($setId)) {
            if (!empty($setId)) {
                $this->join(
                    ['entity_attribute' => $this->getTable('eav_entity_attribute')],
                    'entity_attribute.attribute_id = main_table.attribute_id',
                    'attribute_id'
                );
                $this->addFieldToFilter('entity_attribute.attribute_set_id', ['in' => $setId]);
                $this->addAttributeGrouping();
            }
        } elseif ($setId) {
            $this->join(
                ['entity_attribute' => $this->getTable('eav_entity_attribute')],
                'entity_attribute.attribute_id = main_table.attribute_id'
            );
            $this->addFieldToFilter('entity_attribute.attribute_set_id', $setId);
            $this->setOrder('entity_attribute.sort_order', self::SORT_ORDER_ASC);
        }

        return $this;
    }

    /**
     * Add attribute set filter to collection based on attribute set name and corresponding entity type.
     *
     * @param string $attributeSetName
     * @param string $entityTypeCode
     * @return void
     */
    public function setAttributeSetFilterBySetName($attributeSetName, $entityTypeCode)
    {
        //@codeCoverageIgnoreStart
        $entityTypeId = $this->eavConfig->getEntityType($entityTypeCode)->getId();
        $this->join(
            ['entity_attribute' => $this->getTable('eav_entity_attribute')],
            'entity_attribute.attribute_id = main_table.attribute_id'
        );
        $this->join(
            ['attribute_set' => $this->getTable('eav_attribute_set')],
            'attribute_set.attribute_set_id = entity_attribute.attribute_set_id',
            []
        );
        $this->addFieldToFilter('attribute_set.entity_type_id', $entityTypeId);
        $this->addFieldToFilter('attribute_set.attribute_set_name', $attributeSetName);
        $this->setOrder('entity_attribute.sort_order', self::SORT_ORDER_ASC);
        //@codeCoverageIgnoreEnd
    }

    /**
     * Specify multiple attribute sets filter
     * Result will be ordered by sort_order
     *
     * @param array $setIds
     * @return $this
     */
    public function setAttributeSetsFilter(array $setIds)
    {
        $this->getSelect()->distinct(true);
        $this->join(
            ['entity_attribute' => $this->getTable('eav_entity_attribute')],
            'entity_attribute.attribute_id = main_table.attribute_id',
            'attribute_id'
        );
        $this->addFieldToFilter('entity_attribute.attribute_set_id', ['in' => $setIds]);
        $this->setOrder('sort_order', self::SORT_ORDER_ASC);

        return $this;
    }

    /**
     * Filter for selecting of attributes that is in all sets
     *
     * @param int[] $setIds
     * @return $this
     */
    public function setInAllAttributeSetsFilter(array $setIds)
    {
        foreach ($setIds as $setId) {
            $setId = (int)$setId;
            if (!$setId) {
                continue;
            }
            $alias = sprintf('entity_attribute_%d', $setId);
            $joinCondition = $this->getConnection()->quoteInto(
                "{$alias}.attribute_id = main_table.attribute_id AND {$alias}.attribute_set_id =?",
                $setId
            );
            $this->join([$alias => 'eav_entity_attribute'], $joinCondition, 'attribute_id');
        }

        //$this->getSelect()->distinct(true);
        $this->setOrder('is_user_defined', self::SORT_ORDER_ASC);

        return $this;
    }

    /**
     * Exclude attributes filter
     *
     * @param array $attributes
     * @return $this
     */
    public function setAttributesExcludeFilter($attributes)
    {
        return $this->addFieldToFilter('main_table.attribute_id', ['nin' => $attributes]);
    }

    /**
     * Specify exclude attribute set filter
     *
     * @param int $setId
     * @return $this
     */
    public function setExcludeSetFilter($setId)
    {
        $existsSelect = $this->getConnection()->select()->from(
            ['entity_attribute' => $this->getTable('eav_entity_attribute')]
        )->where(
            'entity_attribute.attribute_set_id = ?',
            $setId
        );
        $this->getSelect()->order('attribute_id ' . self::SORT_ORDER_DESC);

        $this->getSelect()->exists($existsSelect, 'entity_attribute.attribute_id = main_table.attribute_id', false);
        return $this;
    }

    /**
     * Filter by attribute group id
     *
     * @param int $groupId
     * @return $this
     */
    public function setAttributeGroupFilter($groupId)
    {
        $this->join(
            ['entity_attribute' => $this->getTable('eav_entity_attribute')],
            'entity_attribute.attribute_id = main_table.attribute_id'
        );
        $this->addFieldToFilter('entity_attribute.attribute_group_id', $groupId);
        $this->setOrder('sort_order', self::SORT_ORDER_ASC);

        return $this;
    }

    /**
     * Declare group by attribute id condition for collection select
     *
     * @return $this
     */
    public function addAttributeGrouping()
    {
        $this->getSelect()->group('main_table.attribute_id');
        return $this;
    }

    /**
     * Specify "is_unique" filter as true
     *
     * @return $this
     */
    public function addIsUniqueFilter()
    {
        return $this->addFieldToFilter('is_unique', ['gt' => 0]);
    }

    /**
     * Specify "is_unique" filter as false
     *
     * @return $this
     */
    public function addIsNotUniqueFilter()
    {
        return $this->addFieldToFilter('is_unique', 0);
    }

    /**
     * Specify filter to select just attributes with options
     *
     * @return $this
     */
    public function addHasOptionsFilter()
    {
        $adapter = $this->getConnection();
        $orWhere = implode(
            ' OR ',
            [
                $adapter->quoteInto('(main_table.frontend_input = ? AND ao.option_id > 0)', 'select'),
                $adapter->quoteInto('(main_table.frontend_input <> ?)', 'select'),
                '(main_table.is_user_defined = 0)'
            ]
        );

        $this->getSelect()->joinLeft(
            ['ao' => $this->getTable('eav_attribute_option')],
            'ao.attribute_id = main_table.attribute_id',
            'option_id'
        )->group(
            'main_table.attribute_id'
        )->where(
            $orWhere
        );
        return $this;
    }

    /**
     * Apply filter by attribute frontend input type
     *
     * @param string $frontendInputType
     * @return $this
     */
    public function setFrontendInputTypeFilter($frontendInputType)
    {
        return $this->addFieldToFilter('frontend_input', $frontendInputType);
    }

    /**
     * Flag for adding information about attributes sets to result
     *
     * @param bool $flag
     * @return $this
     */
    public function addSetInfo($flag = true)
    {
        $this->_addSetInfoFlag = (bool)$flag;
        return $this;
    }

    /**
     * Ad information about attribute sets to collection result data
     *
     * @return $this
     */
    protected function _addSetInfo()
    {
        if ($this->_addSetInfoFlag) {
            $attributeIds = [];
            foreach ($this->_data as &$dataItem) {
                $attributeIds[] = $dataItem['attribute_id'];
            }
            $attributeToSetInfo = [];

            $adapter = $this->getConnection();
            if (count($attributeIds) > 0) {
                $select = $adapter->select()->from(
                    ['entity' => $this->getTable('eav_entity_attribute')],
                    ['attribute_id', 'attribute_set_id', 'attribute_group_id', 'sort_order']
                )->joinLeft(
                    ['attribute_group' => $this->getTable('eav_attribute_group')],
                    'entity.attribute_group_id = attribute_group.attribute_group_id',
                    ['group_sort_order' => 'sort_order']
                )->where(
                    'attribute_id IN (?)',
                    $attributeIds
                );
                $result = $adapter->fetchAll($select);

                foreach ($result as $row) {
                    $data = [
                        'group_id' => $row['attribute_group_id'],
                        'group_sort' => $row['group_sort_order'],
                        'sort' => $row['sort_order'],
                    ];
                    $attributeToSetInfo[$row['attribute_id']][$row['attribute_set_id']] = $data;
                }
            }

            foreach ($this->_data as &$attributeData) {
                $setInfo = [];
                if (isset($attributeToSetInfo[$attributeData['attribute_id']])) {
                    $setInfo = $attributeToSetInfo[$attributeData['attribute_id']];
                }

                $attributeData['attribute_set_info'] = $setInfo;
            }

            unset($attributeToSetInfo);
            unset($attributeIds);
        }
        return $this;
    }

    /**
     * Ad information about attribute sets to collection result data
     *
     * @return \Magento\Framework\Model\Resource\Db\Collection\AbstractCollection
     */
    protected function _afterLoadData()
    {
        $this->_addSetInfo();

        return parent::_afterLoadData();
    }

    /**
     * Specify collection attribute codes filter
     *
     * @param string|array $code
     * @return $this
     */
    public function setCodeFilter($code)
    {
        if (empty($code)) {
            return $this;
        }
        if (!is_array($code)) {
            $code = [$code];
        }

        return $this->addFieldToFilter('attribute_code', ['in' => $code]);
    }

    /**
     * Add store label to attribute by specified store id
     *
     * @param int $storeId
     * @return $this
     */
    public function addStoreLabel($storeId)
    {
        $adapter = $this->getConnection();
        $joinExpression = $adapter->quoteInto(
            'al.attribute_id = main_table.attribute_id AND al.store_id = ?',
            (int)$storeId
        );
        $this->getSelect()->joinLeft(
            ['al' => $this->getTable('eav_attribute_label')],
            $joinExpression,
            ['store_label' => $adapter->getIfNullSql('al.value', 'main_table.frontend_label')]
        );

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getSelectCountSql()
    {
        $countSelect = parent::getSelectCountSql();
        $countSelect->reset(\Zend_Db_Select::COLUMNS);
        $countSelect->columns('COUNT(DISTINCT main_table.attribute_id)');
        return $countSelect;
    }
}
