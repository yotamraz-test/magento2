<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Model\Tag;

use Magento\Framework\Model\AbstractModel;
use Magento\Tag\Model\ResourceModel\Tag\Relation as RelationResource;

/**
 * Tag relation model
 *
 * Represents the relationship between a tag, a customer, a product, and a store.
 *
 * @method int getActive()
 * @method Relation setActive(int $value)
 * @method array getAddedProductIds()
 * @method Relation setAddedProductIds(array $value)
 * @method int|null getCustomerId()
 * @method Relation setCustomerId(?int $value)
 * @method int getProductId()
 * @method Relation setProductId(int $value)
 * @method Relation setProductIds(array $value)
 * @method Relation setRelatedTagIds(array $value)
 * @method string getStatusFilter()
 * @method Relation setStatusFilter(string $value)
 * @method int getStoreId()
 * @method Relation setStoreId(int $value)
 * @method int getTagId()
 * @method Relation setTagId(int $value)
 * @method bool hasStoreId()
 */
class Relation extends AbstractModel
{
    /**
     * Relation statuses
     */
    public const STATUS_ACTIVE = 1;
    public const STATUS_NOT_ACTIVE = 0;

    /**
     * Entity code
     */
    public const ENTITY = 'tag_relation';

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(RelationResource::class);
    }

    /**
     * Load relation by tag, customer, product, and store
     *
     * @param int $tagId
     * @param int $customerId
     * @param int $productId
     * @param int $storeId
     * @return $this
     */
    public function loadByTagCustomerProduct(int $tagId, int $customerId, int $productId, int $storeId): self
    {
        $this->setData('product_id', $productId);
        $this->setData('tag_id', $tagId);
        $this->setData('customer_id', $customerId);
        $this->setData('store_id', $storeId);
        $this->_getResource()->loadByTagCustomer($this);
        return $this;
    }

    /**
     * Retrieve relation product IDs
     *
     * @return array
     */
    public function getProductIds(): array
    {
        $ids = $this->getDataByKey('product_ids');
        if ($ids === null) {
            $ids = $this->_getResource()->getProductIds($this);
            $this->setData('product_ids', $ids);
        }
        return (array)$ids;
    }

    /**
     * Retrieve list of related tag IDs for products specified in current object
     *
     * @return array
     */
    public function getRelatedTagIds(): array
    {
        if ($this->getDataByKey('related_tag_ids') === null) {
            $this->setData('related_tag_ids', $this->_getResource()->getRelatedTagIds($this));
        }
        return (array)$this->getDataByKey('related_tag_ids');
    }

    /**
     * Deactivate tag relation (soft delete)
     *
     * @return $this
     */
    public function deactivate(): self
    {
        $this->setData('active', self::STATUS_NOT_ACTIVE);
        $this->save();
        return $this;
    }

    /**
     * Activate tag relation
     *
     * @return $this
     */
    public function activate(): self
    {
        $this->setData('active', self::STATUS_ACTIVE);
        $this->save();
        return $this;
    }

    /**
     * Create relations for all combinations of tagIds x productIds for the given customer and store.
     *
     * Delegates the actual insertion / replace to the resource model, which handles the diff
     * computation (inserting new rows and deleting rows no longer present in $productIds).
     * `$customerId` may be null for admin-authored assignments (no owning customer); the value
     * is passed through to the resource layer as-is — do not coerce null to 0.
     *
     * @param array $tagIds
     * @param array $productIds
     * @param int|null $customerId Null for admin-authored assignments.
     * @param int $storeId
     * @return $this
     */
    public function addRelations(array $tagIds, array $productIds, ?int $customerId, int $storeId): self
    {
        $this->setData('added_product_ids', $productIds);
        $this->setData('tag_id', count($tagIds) === 1 ? reset($tagIds) : null);
        $this->setData('related_tag_ids', $tagIds);
        $this->setData('customer_id', $customerId);
        $this->setData('store_id', $storeId);
        $this->_getResource()->addRelations($this);
        return $this;
    }
}
