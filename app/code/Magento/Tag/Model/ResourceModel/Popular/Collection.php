<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Model\ResourceModel\Popular;

use Magento\Framework\DB\Select;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Tag\Api\Data\TagInterface;
use Magento\Tag\Model\ResourceModel\Tag as TagResource;
use Magento\Tag\Model\Tag as TagModel;

/**
 * Popular tags collection.
 *
 * Ported from legacy Mage_Tag_Model_Resource_Popular_Collection.
 */
class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(TagModel::class, TagResource::class);
    }

    /**
     * Build the select from tag_summary joined with tag, filtering to approved tags with products.
     *
     * @return $this
     */
    protected function _initSelect(): self
    {
        $this->getSelect()
            ->from(
                ['main_table' => $this->getTable('tag_summary')],
                ['popularity' => 'main_table.popularity']
            )
            ->joinInner(
                ['tag' => $this->getTable('tag')],
                sprintf(
                    'tag.tag_id = main_table.tag_id AND tag.status = %d',
                    TagInterface::STATUS_APPROVED
                ),
                ['tag_id', 'name', 'status', 'first_customer_id', 'first_store_id']
            )
            ->where('main_table.products > ?', 0)
            ->order('popularity ' . Select::SQL_DESC);

        return $this;
    }

    /**
     * Scope the collection to a single store.
     *
     * @param int $storeId
     * @return $this
     */
    public function addStoreFilter(int $storeId): self
    {
        $this->getSelect()->where('main_table.store_id = ?', $storeId);
        return $this;
    }

    /**
     * Restrict to tags with a particular status (defaults are already applied in _initSelect()).
     *
     * @param int $status
     * @return $this
     */
    public function addStatusFilter(int $status): self
    {
        $this->getSelect()->where('tag.status = ?', $status);
        return $this;
    }

    /**
     * Count distinct popular tags, ignoring ORDER / LIMIT.
     *
     * @return Select
     */
    public function getSelectCountSql()
    {
        $this->_renderFilters();
        $select = clone $this->getSelect();
        $select->reset(Select::ORDER);
        $select->reset(Select::LIMIT_COUNT);
        $select->reset(Select::LIMIT_OFFSET);

        $countSelect = $this->getConnection()->select();
        $countSelect->from(['a' => $select], 'COUNT(a.popularity)');
        return $countSelect;
    }
}
