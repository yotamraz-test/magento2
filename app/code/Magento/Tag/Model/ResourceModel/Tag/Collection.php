<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Model\ResourceModel\Tag;

use Magento\Framework\DB\Select;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Tag\Model\ResourceModel\Tag as TagResource;
use Magento\Tag\Model\Tag as TagModel;

/**
 * Tag collection resource model.
 *
 * Ported from legacy Mage_Tag_Model_Resource_Tag_Collection.
 */
class Collection extends AbstractCollection
{
    /**
     * Field map used by the collection base class.
     *
     * @var array
     */
    protected $_map = [
        'fields' => [
            'tag_id' => 'main_table.tag_id',
        ],
    ];

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(TagModel::class, TagResource::class);
    }

    /**
     * Add tag popularity to the select by joining tag_summary.
     *
     * @param int|null $storeId Scope the summary join by store if provided.
     * @return $this
     */
    public function addPopularity(?int $storeId = null): self
    {
        if ($this->getFlag('popularity')) {
            return $this;
        }

        $joinCondition = 'main_table.tag_id = summary.tag_id';
        if ($storeId !== null) {
            $joinCondition .= $this->getConnection()
                ->quoteInto(' AND summary.store_id = ?', $storeId);
        }

        $this->getSelect()
            ->joinLeft(
                ['summary' => $this->getTable('tag_summary')],
                $joinCondition,
                ['popularity' => 'summary.popularity']
            )
            ->group('main_table.tag_id');

        $this->_useAnalyticFunction = true;
        $this->setFlag('popularity', true);
        return $this;
    }

    /**
     * Add tag_summary columns (store_id, popularity, customers, products, uses) to the select.
     *
     * @param int|null $storeId Scope the summary join by store if provided.
     * @return $this
     */
    public function addSummary(?int $storeId = null): self
    {
        if ($this->getFlag('summary')) {
            return $this;
        }

        $joinCondition = 'main_table.tag_id = summary.tag_id';
        if ($storeId !== null) {
            $joinCondition .= $this->getConnection()
                ->quoteInto(' AND summary.store_id = ?', $storeId);
        }

        $this->getSelect()->joinLeft(
            ['summary' => $this->getTable('tag_summary')],
            $joinCondition,
            ['store_id', 'popularity', 'customers', 'products', 'uses']
        );

        $this->addFilterToMap('store_id', 'summary.store_id');
        $this->addFilterToMap('popularity', 'summary.popularity');
        $this->addFilterToMap('customers', 'summary.customers');
        $this->addFilterToMap('products', 'summary.products');
        $this->addFilterToMap('uses', 'summary.uses');

        $this->setFlag('summary', true);
        return $this;
    }

    /**
     * Attach a comma-separated list of store ids each tag is visible in.
     *
     * @return $this
     */
    public function addStoresVisibility(): self
    {
        if ($this->getFlag('stores_visibility')) {
            return $this;
        }

        $this->getSelect()
            ->joinLeft(
                ['stores_relation' => $this->getTable('tag_relation')],
                'main_table.tag_id = stores_relation.tag_id',
                []
            )
            ->joinLeft(
                ['stores_store' => $this->getTable('store')],
                'stores_store.store_id = stores_relation.store_id',
                ['stores' => new \Zend_Db_Expr('GROUP_CONCAT(DISTINCT stores_store.store_id)')]
            )
            ->group('main_table.tag_id');

        $this->_useAnalyticFunction = true;
        $this->setFlag('stores_visibility', true);
        return $this;
    }

    /**
     * Filter the collection by the stores a tag belongs to via tag_summary.
     *
     * @param array|int $storeId
     * @param bool $allFilter When true, also apply the filter to prior relation joins (legacy parity).
     * @return $this
     */
    public function addStoreFilter(array|int $storeId, bool $allFilter = true): self
    {
        if (!$this->getFlag('store_filter')) {
            $this->getSelect()->joinLeft(
                ['summary_store' => $this->getTable('tag_summary')],
                'main_table.tag_id = summary_store.tag_id',
                []
            );
            $this->getSelect()
                ->where('summary_store.store_id IN (?)', $storeId)
                ->group('main_table.tag_id');

            if ($allFilter && $this->getFlag('relation')) {
                $this->getSelect()->where('relation.store_id IN (?)', $storeId);
            }

            $this->_useAnalyticFunction = true;
            $this->setFlag('store_filter', true);
        }

        return $this;
    }

    /**
     * Add WHERE main_table.status IN (...).
     *
     * @param int|array $status
     * @return $this
     */
    public function addStatusFilter(int|array $status): self
    {
        $this->getSelect()->where('main_table.status IN (?)', $status);
        return $this;
    }

    /**
     * Restrict the collection to tags attached to the given product.
     *
     * @param int $productId
     * @return $this
     */
    public function addProductFilter(int $productId): self
    {
        if (!$this->getFlag('relation')) {
            $this->joinRel();
        }
        $this->getSelect()->where('relation.product_id = ?', $productId);
        return $this;
    }

    /**
     * Restrict the collection to tags owned by the given customer.
     *
     * @param int $customerId
     * @return $this
     */
    public function addCustomerFilter(int $customerId): self
    {
        if (!$this->getFlag('relation')) {
            $this->joinRel();
        }
        $this->getSelect()->where('relation.customer_id = ?', $customerId);
        return $this;
    }

    /**
     * Join tag_relation as "relation".
     *
     * @return $this
     */
    public function joinRel(): self
    {
        if ($this->getFlag('relation')) {
            return $this;
        }

        $this->getSelect()->joinLeft(
            ['relation' => $this->getTable('tag_relation')],
            'main_table.tag_id = relation.tag_id',
            []
        );
        $this->setFlag('relation', true);
        return $this;
    }

    /**
     * Ensure count queries ignore the grouping/having we add for aggregates.
     *
     * @return Select
     */
    public function getSelectCountSql()
    {
        $select = parent::getSelectCountSql();
        $select->reset(Select::COLUMNS);
        $select->reset(Select::GROUP);
        $select->reset(Select::HAVING);
        $select->columns('COUNT(DISTINCT main_table.tag_id)');
        return $select;
    }
}
