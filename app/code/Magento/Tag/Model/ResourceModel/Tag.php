<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Model\ResourceModel;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Tag\Model\Tag as TagModel;

/**
 * Tag resource model.
 *
 * Ported from legacy Mage_Tag_Model_Resource_Tag. The aggregate() method is implemented as a
 * modern INSERT ... SELECT pipeline producing per-store and global (store_id = 0) summary rows,
 * following the structure described in the foundation milestone spec.
 */
class Tag extends AbstractDb
{
    /**
     * @param Context $context
     * @param EavConfig $eavConfig
     * @param string|null $connectionName
     */
    public function __construct(
        Context $context,
        private readonly EavConfig $eavConfig,
        ?string $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
    }

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init('tag', 'tag_id');
    }

    /**
     * Load a tag row by its name into the given object.
     *
     * @param AbstractModel $object
     * @param string $name
     * @return $this
     */
    public function loadByName(AbstractModel $object, string $name): self
    {
        if ($name === '') {
            return $this;
        }

        $name = mb_substr($name, 0, 255);

        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('name = :name');

        $data = $connection->fetchRow($select, ['name' => $name]);
        $object->setData(is_array($data) ? $data : []);

        return $this;
    }

    /**
     * Rebuild tag_summary rows for the given tag (or every tag if the model has no id).
     *
     * Deletes existing summary rows for scope, inserts fresh per-store rows and a global
     * (store_id = 0) row, then cleans up summary rows for tags that no longer have any
     * tag_relation rows at all.
     *
     * @param TagModel $tagModel
     * @return $this
     */
    public function aggregate(TagModel $tagModel): self
    {
        $connection = $this->getConnection();
        $summaryTable = $this->getTable('tag_summary');
        $relationTable = $this->getTable('tag_relation');
        $tagId = (int)$tagModel->getId();

        // 1) Delete existing summary rows for the requested scope.
        $deleteWhere = [];
        if ($tagId > 0) {
            $deleteWhere['tag_id = ?'] = $tagId;
        }
        $connection->delete($summaryTable, $deleteWhere);

        $insertColumns = [
            'tag_id',
            'store_id',
            'customers',
            'products',
            'uses',
            'historical_uses',
            'popularity',
            'base_popularity',
        ];

        // 2) Insert per-store aggregate rows.
        $connection->query(
            $connection->insertFromSelect(
                $this->buildAggregateSelect($tagId, false),
                $summaryTable,
                $insertColumns,
                AdapterInterface::INSERT_ON_DUPLICATE
            )
        );

        // 3) Insert the global (store_id = 0) aggregate row.
        $connection->query(
            $connection->insertFromSelect(
                $this->buildAggregateSelect($tagId, true),
                $summaryTable,
                $insertColumns,
                AdapterInterface::INSERT_ON_DUPLICATE
            )
        );

        // 4) Delete summary rows for tags that no longer have any tag_relation rows.
        $existsSubquery = $connection->select()
            ->from($relationTable, [new \Zend_Db_Expr('1')])
            ->where(sprintf('%s.tag_id = %s.tag_id', $relationTable, $summaryTable))
            ->limit(1);
        $connection->query(sprintf(
            'DELETE FROM %s WHERE NOT EXISTS (%s)',
            $connection->quoteIdentifier($summaryTable),
            (string)$existsSubquery
        ));

        return $this;
    }

    /**
     * Build the INSERT ... SELECT source select for aggregate().
     *
     * @param int $tagId Restrict to this tag if > 0.
     * @param bool $globalRow true to emit the store_id = 0 global row, false for per-store rows.
     * @return Select
     */
    private function buildAggregateSelect(int $tagId, bool $globalRow): Select
    {
        $connection = $this->getConnection();
        $relationTable = $this->getTable('tag_relation');
        $storeTable = $this->getTable('store');
        $productWebsiteTable = $this->getTable('catalog_product_website');
        $productEntityTable = $this->getTable('catalog_product_entity');
        $propertiesTable = $this->getTable('tag_properties');

        $select = $connection->select()
            ->from(['trel' => $relationTable], [])
            ->join(
                ['s' => $storeTable],
                's.store_id = trel.store_id AND s.store_id > 0',
                []
            )
            ->join(
                ['pw' => $productWebsiteTable],
                'pw.website_id = s.website_id AND pw.product_id = trel.product_id',
                []
            )
            ->join(
                ['pe' => $productEntityTable],
                'pe.entity_id = trel.product_id',
                []
            )
            ->joinLeft(
                ['tp' => $propertiesTable],
                $globalRow
                    ? 'tp.tag_id = trel.tag_id'
                    : 'tp.tag_id = trel.tag_id AND tp.store_id = trel.store_id',
                []
            )
            ->where('trel.active = ?', 1);

        if ($tagId > 0) {
            $select->where('trel.tag_id = ?', $tagId);
        }

        // Catalog product status / visibility filters (EAV, default-scope row).
        $statusAttr = $this->eavConfig->getAttribute(Product::ENTITY, ProductInterface::STATUS);
        if ($statusAttr && $statusAttr->getAttributeId()) {
            $select->join(
                ['pstat' => $this->getTable('catalog_product_entity_int')],
                sprintf(
                    'pstat.entity_id = pe.entity_id AND pstat.attribute_id = %d AND pstat.store_id = 0',
                    (int)$statusAttr->getAttributeId()
                ),
                []
            )->where('pstat.value = ?', ProductStatus::STATUS_ENABLED);
        }

        $visibilityAttr = $this->eavConfig->getAttribute(Product::ENTITY, 'visibility');
        if ($visibilityAttr && $visibilityAttr->getAttributeId()) {
            $select->join(
                ['pvis' => $this->getTable('catalog_product_entity_int')],
                sprintf(
                    'pvis.entity_id = pe.entity_id AND pvis.attribute_id = %d AND pvis.store_id = 0',
                    (int)$visibilityAttr->getAttributeId()
                ),
                []
            )->where('pvis.value <> ?', Visibility::VISIBILITY_NOT_VISIBLE);
        }

        $select->columns([
            'tag_id' => 'trel.tag_id',
            'store_id' => $globalRow ? new \Zend_Db_Expr('0') : 'trel.store_id',
            'customers' => new \Zend_Db_Expr('COUNT(DISTINCT trel.customer_id)'),
            'products' => new \Zend_Db_Expr('COUNT(DISTINCT trel.product_id)'),
            'uses' => new \Zend_Db_Expr('COUNT(trel.tag_relation_id)'),
            'historical_uses' => new \Zend_Db_Expr('COUNT(trel.tag_relation_id)'),
            'popularity' => new \Zend_Db_Expr(
                'COUNT(DISTINCT trel.customer_id) + COALESCE(MIN(tp.base_popularity), 0)'
            ),
            'base_popularity' => new \Zend_Db_Expr('COALESCE(MIN(tp.base_popularity), 0)'),
        ]);

        $select->group(
            $globalRow ? ['trel.tag_id'] : ['trel.tag_id', 'trel.store_id']
        );

        return $select;
    }

    /**
     * Decrement product/use counters for every summary row touching the given product.
     *
     * @param int $productId
     * @return int Number of affected summary rows.
     */
    public function decrementProducts(int $productId): int
    {
        $connection = $this->getConnection();
        $summaryTable = $this->getTable('tag_summary');
        $relationTable = $this->getTable('tag_relation');

        $tagIdsSelect = $connection->select()
            ->distinct()
            ->from($relationTable, ['tag_id'])
            ->where('product_id = ?', $productId);
        $tagIds = $connection->fetchCol($tagIdsSelect);

        if ($tagIds === []) {
            return 0;
        }

        return $connection->update(
            $summaryTable,
            [
                'products' => new \Zend_Db_Expr('products - 1'),
                'uses' => new \Zend_Db_Expr('uses - 1'),
            ],
            ['tag_id IN (?)' => $tagIds]
        );
    }

    /**
     * Upsert the base popularity property row when the saved tag carries one.
     *
     * @param AbstractModel $object
     * @return $this
     */
    protected function _afterSave(AbstractModel $object): self
    {
        if ($object->hasData('base_popularity') && $object->hasData('store_id')) {
            $tagId = (int)$object->getId();
            if ($tagId > 0) {
                $this->getConnection()->insertOnDuplicate(
                    $this->getTable('tag_properties'),
                    [
                        'tag_id' => $tagId,
                        'store_id' => (int)$object->getData('store_id'),
                        'base_popularity' => (int)$object->getData('base_popularity'),
                    ],
                    ['base_popularity']
                );
            }
        }

        return parent::_afterSave($object);
    }
}
