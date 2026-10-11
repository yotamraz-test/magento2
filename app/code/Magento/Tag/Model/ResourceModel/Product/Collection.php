<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Model\ResourceModel\Product;

use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;

/**
 * Tagged product collection.
 *
 * Ported from legacy Mage_Tag_Model_Resource_Product_Collection. Adds helpers for filtering
 * catalog products through the tag_relation table.
 */
class Collection extends ProductCollection
{
    /**
     * Filter the product collection to those linked to a tag by the given tag id.
     *
     * @param int $tagId
     * @return $this
     */
    public function addTagFilter(int $tagId): self
    {
        $this->joinTagRelation();
        $this->getSelect()->where('tag_relation.tag_id = ?', $tagId);
        $this->setFlag('distinct', true);
        return $this;
    }

    /**
     * Filter the product collection to those linked by the given customer.
     *
     * @param int $customerId
     * @return $this
     */
    public function addCustomerFilter(int $customerId): self
    {
        $this->joinTagRelation();
        $this->getSelect()->where('tag_relation.customer_id = ?', $customerId);
        return $this;
    }

    /**
     * Filter the product collection to those tagged in the given store.
     *
     * @param int $storeId
     * @return $this
     */
    public function addStoreFilter($storeId = null)
    {
        if ($storeId !== null) {
            $this->joinTagRelation();
            $this->getSelect()->where('tag_relation.store_id IN (?)', $storeId);
        }
        return $this;
    }

    /**
     * Join tag_relation onto the product collection select (once).
     *
     * @return $this
     */
    public function joinTagRelation(): self
    {
        if ($this->getFlag('tag_relation_joined')) {
            return $this;
        }

        $this->getSelect()->join(
            ['tag_relation' => $this->getTable('tag_relation')],
            'tag_relation.product_id = e.entity_id',
            []
        );
        $this->setFlag('tag_relation_joined', true);
        return $this;
    }
}
