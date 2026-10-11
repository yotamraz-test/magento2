<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Model\ResourceModel\Tag;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Tag\Model\Tag\Relation as RelationModel;

/**
 * Tag Relation resource model.
 *
 * Ported from legacy Mage_Tag_Model_Resource_Tag_Relation.
 */
class Relation extends AbstractDb
{
    /**
     * @param Context $context
     * @param DateTime $date
     * @param string|null $connectionName
     */
    public function __construct(
        Context $context,
        private readonly DateTime $date,
        ?string $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
    }

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init('tag_relation', 'tag_relation_id');
    }

    /**
     * Load the first relation matching the (tag, customer, product, store) tuple on the object.
     *
     * @param RelationModel $object
     * @return $this
     */
    public function loadByTagCustomer(RelationModel $object): self
    {
        $tagId = (int)$object->getTagId();
        $customerId = $object->getData('customer_id');
        if ($tagId === 0 || $customerId === null) {
            return $this;
        }

        $connection = $this->getConnection();
        $bind = [
            'tag_id' => $tagId,
            'customer_id' => (int)$customerId,
        ];

        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('tag_id = :tag_id')
            ->where('customer_id = :customer_id');

        if ($object->getProductId()) {
            $select->where('product_id = :product_id');
            $bind['product_id'] = (int)$object->getProductId();
        }

        if ($object->hasStoreId()) {
            $select->where('store_id = :store_id');
            $bind['store_id'] = (int)$object->getStoreId();
        }

        $data = $connection->fetchRow($select, $bind);
        $object->setData(is_array($data) ? $data : []);

        return $this;
    }

    /**
     * Return product ids referenced by this relation's tag, honoring customer/store filters.
     *
     * @param RelationModel $object
     * @return array
     */
    public function getProductIds(RelationModel $object): array
    {
        $tagId = (int)$object->getTagId();
        if ($tagId === 0) {
            return [];
        }

        $connection = $this->getConnection();
        $bind = ['tag_id' => $tagId];

        $select = $connection->select()
            ->distinct()
            ->from($this->getMainTable(), ['product_id'])
            ->where($this->getMainTable() . '.tag_id = :tag_id');

        $customerId = $object->getData('customer_id');
        if ($customerId !== null) {
            $select->where($this->getMainTable() . '.customer_id = :customer_id');
            $bind['customer_id'] = (int)$customerId;
        }

        if ($object->hasStoreId()) {
            $select->where($this->getMainTable() . '.store_id = :store_id');
            $bind['store_id'] = (int)$object->getStoreId();
        }

        $statusFilter = $object->getStatusFilter();
        if ($statusFilter !== null) {
            $select->join(
                ['t' => $this->getTable('tag')],
                't.tag_id = ' . $this->getMainTable() . '.tag_id',
                []
            )->where('t.status = :t_status');
            $bind['t_status'] = $statusFilter;
        } else {
            $select->where($this->getMainTable() . '.active = ?', RelationModel::STATUS_ACTIVE);
        }

        return $connection->fetchCol($select, $bind);
    }

    /**
     * Return the distinct tag_ids that already exist for the model's added products / scope.
     *
     * @param RelationModel $object
     * @return array
     */
    public function getRelatedTagIds(RelationModel $object): array
    {
        $productIds = $object->getData('added_product_ids');
        if ($productIds === null) {
            $raw = $object->getProductId();
            $productIds = is_array($raw) ? $raw : ($raw === null ? [] : [$raw]);
        }
        $productIds = array_values(array_filter(array_map('intval', (array)$productIds)));
        if ($productIds === []) {
            return [];
        }

        $connection = $this->getConnection();
        $select = $connection->select()
            ->distinct()
            ->from($this->getMainTable(), ['tag_id'])
            ->where('product_id IN (?)', $productIds)
            ->order('tag_id');

        $customerId = $object->getData('customer_id');
        if ($customerId !== null) {
            $select->where('customer_id = ?', (int)$customerId);
        }

        if ($object->hasStoreId()) {
            $select->where('store_id = ?', (int)$object->getStoreId());
        }

        return $connection->fetchCol($select);
    }

    /**
     * Diff-based inserter for tag/product relations.
     *
     * Loads the currently-related tag_ids for the model's (customer, store, added_product_ids)
     * tuple, computes the set of (tag_id, product_id) pairs that are new, and inserts them
     * with ON DUPLICATE KEY UPDATE so re-saving an already-known relation re-activates it.
     *
     * @param RelationModel $object
     * @return $this
     */
    public function addRelations(RelationModel $object): self
    {
        $customerId = $object->getData('customer_id');
        $storeId = (int)$object->getStoreId();
        $addedProductIds = array_values(array_filter(array_map(
            'intval',
            (array)$object->getData('added_product_ids')
        )));

        $tagIds = (array)$object->getData('related_tag_ids');
        if ($tagIds === [] && $object->getTagId()) {
            $tagIds = [(int)$object->getTagId()];
        }
        $tagIds = array_values(array_filter(array_map('intval', $tagIds)));

        if ($addedProductIds === [] || $tagIds === []) {
            return $this;
        }

        $connection = $this->getConnection();
        $mainTable = $this->getMainTable();

        // Current (tag_id, product_id) set for scope.
        $select = $connection->select()
            ->from($mainTable, ['tag_id', 'product_id'])
            ->where('tag_id IN (?)', $tagIds)
            ->where('product_id IN (?)', $addedProductIds)
            ->where('store_id = ?', $storeId);
        if ($customerId !== null) {
            $select->where('customer_id = ?', (int)$customerId);
        } else {
            $select->where('customer_id IS NULL');
        }
        $existing = [];
        foreach ($connection->fetchAll($select) as $row) {
            $existing[(int)$row['tag_id']][(int)$row['product_id']] = true;
        }

        $createdAt = $this->date->gmtDate();
        $insertRows = [];
        foreach ($tagIds as $tagId) {
            foreach ($addedProductIds as $productId) {
                if (isset($existing[$tagId][$productId])) {
                    continue;
                }
                $insertRows[] = [
                    'tag_id' => $tagId,
                    'customer_id' => $customerId === null ? null : (int)$customerId,
                    'product_id' => $productId,
                    'store_id' => $storeId,
                    'active' => RelationModel::STATUS_ACTIVE,
                    'created_at' => $createdAt,
                ];
            }
        }

        if ($insertRows !== []) {
            $connection->insertOnDuplicate(
                $mainTable,
                $insertRows,
                ['active', 'created_at']
            );
        }

        return $this;
    }

    /**
     * Deactivate (soft-delete) relations for the given tag ids owned by the given customer.
     *
     * @param array $tagIds
     * @param int $customerId
     * @return $this
     */
    public function deactivate(array $tagIds, int $customerId): self
    {
        $tagIds = array_values(array_filter(array_map('intval', $tagIds)));
        if ($tagIds === []) {
            return $this;
        }

        $this->getConnection()->update(
            $this->getMainTable(),
            ['active' => RelationModel::STATUS_NOT_ACTIVE],
            [
                'tag_id IN (?)' => $tagIds,
                'customer_id = ?' => $customerId,
            ]
        );

        return $this;
    }
}
