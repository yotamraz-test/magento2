<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Model\ResourceModel\Tag\Relation;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Tag\Model\ResourceModel\Tag\Relation as RelationResource;
use Magento\Tag\Model\Tag\Relation as RelationModel;

/**
 * Tag Relation collection resource model.
 */
class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(RelationModel::class, RelationResource::class);
    }

    /**
     * @param int $tagId
     * @return $this
     */
    public function addTagFilter(int $tagId): self
    {
        $this->addFieldToFilter('tag_id', $tagId);
        return $this;
    }

    /**
     * @param int $customerId
     * @return $this
     */
    public function addCustomerFilter(int $customerId): self
    {
        $this->addFieldToFilter('customer_id', $customerId);
        return $this;
    }

    /**
     * @param int $productId
     * @return $this
     */
    public function addProductFilter(int $productId): self
    {
        $this->addFieldToFilter('product_id', $productId);
        return $this;
    }

    /**
     * @param int $storeId
     * @return $this
     */
    public function addStoreFilter(int $storeId): self
    {
        $this->addFieldToFilter('store_id', $storeId);
        return $this;
    }

    /**
     * @return $this
     */
    public function addActiveFilter(): self
    {
        $this->addFieldToFilter('active', RelationModel::STATUS_ACTIVE);
        return $this;
    }
}
