<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Model\ResourceModel\Customer;

use Magento\Customer\Model\ResourceModel\Customer\Collection as CustomerCollection;

/**
 * Tagged customer collection.
 *
 * Ported from legacy Mage_Tag_Model_Resource_Customer_Collection. Adds helpers for filtering
 * customers through the tag_relation table.
 */
class Collection extends CustomerCollection
{
    /**
     * Filter the customer collection to those owning a relation for the given tag id.
     *
     * @param int $tagId
     * @return $this
     */
    public function addTagFilter(int $tagId): self
    {
        $this->joinTagRelation();
        $this->getSelect()->where('tag_relation.tag_id = ?', $tagId);
        return $this;
    }

    /**
     * Filter the customer collection to those who tagged the given product.
     *
     * @param int $productId
     * @return $this
     */
    public function addProductFilter(int $productId): self
    {
        $this->joinTagRelation();
        $this->getSelect()->where('tag_relation.product_id = ?', $productId);
        return $this;
    }

    /**
     * Filter the customer collection to those who tagged in the given store.
     *
     * @param int $storeId
     * @return $this
     */
    public function addStoreFilter(int $storeId): self
    {
        $this->joinTagRelation();
        $this->getSelect()->where('tag_relation.store_id = ?', $storeId);
        return $this;
    }

    /**
     * Join tag_relation onto the customer collection select (once).
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
            'tag_relation.customer_id = e.entity_id',
            []
        );
        $this->setFlag('tag_relation_joined', true);
        return $this;
    }
}
