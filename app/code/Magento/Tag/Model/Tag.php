<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Tag\Api\Data\TagInterface;
use Magento\Tag\Model\ResourceModel\Tag as TagResource;
use Magento\Tag\Model\ResourceModel\Tag\Collection;
use Magento\Tag\Model\Tag\Relation;
use Magento\Tag\Model\Tag\RelationFactory;
use Magento\Tag\Model\ResourceModel\Tag\Relation as RelationResource;

/**
 * Tag model
 *
 * @api
 * @since 100.0.2
 *
 * @method int getBasePopularity()
 * @method Tag setBasePopularity(int $value)
 * @method array getStatusFilter()
 * @method Tag setStatusFilter(array $value)
 * @method int getStore()
 * @method Tag setStore(int $value)
 * @method int getStoreId()
 * @method Tag setStoreId(int $value)
 * @method array getVisibleInStoreIds()
 * @method Tag setVisibleInStoreIds(array $value)
 * @method bool hasBasePopularity()
 * @method bool hasStoreId()
 */
class Tag extends AbstractModel implements TagInterface, IdentityInterface
{
    /**
     * Cache tag
     */
    public const CACHE_TAG = 'tag';

    /**
     * Add-relation result status constants
     */
    public const ADD_STATUS_SUCCESS = 'success';
    public const ADD_STATUS_NEW = 'new';
    public const ADD_STATUS_EXIST = 'exist';
    public const ADD_STATUS_REJECTED = 'rejected';

    /**
     * Entity code
     */
    public const ENTITY = 'tag';

    /**
     * Event prefix for observer
     *
     * @var string
     */
    protected $_eventPrefix = 'tag';

    /**
     * Event object key
     *
     * @var string
     */
    protected $_eventObject = 'tag';

    /**
     * @var RelationFactory
     */
    private RelationFactory $relationFactory;

    /**
     * @var RelationResource
     */
    private RelationResource $relationResource;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param TagResource $resource
     * @param Collection|null $resourceCollection
     * @param RelationFactory $relationFactory
     * @param RelationResource $relationResource
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        TagResource $resource,
        ?Collection $resourceCollection,
        RelationFactory $relationFactory,
        RelationResource $relationResource,
        array $data = []
    ) {
        $this->relationFactory = $relationFactory;
        $this->relationResource = $relationResource;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(TagResource::class);
    }

    /**
     * @inheritdoc
     */
    public function getTagId()
    {
        return $this->getData(self::TAG_ID);
    }

    /**
     * @inheritdoc
     */
    public function setTagId($tagId)
    {
        return $this->setData(self::TAG_ID, $tagId);
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return $this->getData(self::NAME);
    }

    /**
     * @inheritdoc
     */
    public function setName($name)
    {
        return $this->setData(self::NAME, $name);
    }

    /**
     * @inheritdoc
     */
    public function getStatus()
    {
        return $this->getData(self::STATUS);
    }

    /**
     * @inheritdoc
     */
    public function setStatus($status)
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * @inheritdoc
     */
    public function getFirstCustomerId()
    {
        return $this->getData(self::FIRST_CUSTOMER_ID);
    }

    /**
     * @inheritdoc
     */
    public function setFirstCustomerId($customerId)
    {
        return $this->setData(self::FIRST_CUSTOMER_ID, $customerId);
    }

    /**
     * @inheritdoc
     */
    public function getFirstStoreId()
    {
        return $this->getData(self::FIRST_STORE_ID);
    }

    /**
     * @inheritdoc
     */
    public function setFirstStoreId($storeId)
    {
        return $this->setData(self::FIRST_STORE_ID, $storeId);
    }

    /**
     * Return unique ID(s) for each object in system
     *
     * @return array
     */
    public function getIdentities(): array
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * Save tag relation with product, customer and store.
     *
     * Ports the exact branching logic from the legacy Mage_Tag_Model_Tag::saveRelation().
     *
     * For APPROVED tags: checks if the product is already tagged in the store (by any customer).
     *   - If yes and current customer already has relation: return 'exist' (reactivate if inactive).
     *   - If yes and current customer has no relation: create new, return 'exist'.
     *   - If no: create new relation, return 'success'.
     *
     * For PENDING tags: check if current customer already has a relation.
     *   - If yes and inactive: reactivate.
     *   - If no: create new.
     *   - Always return 'new'.
     *
     * For DISABLED tags: check if current customer already has a relation for this product.
     *   - If yes: return 'rejected'.
     *   - If no: change tag status to PENDING, save tag, create relation, return 'new'.
     *
     * @param int $customerId
     * @param int $productId
     * @param int $storeId
     * @return string One of ADD_STATUS_SUCCESS, ADD_STATUS_NEW, ADD_STATUS_EXIST, ADD_STATUS_REJECTED
     */
    public function saveRelation(int $customerId, int $productId, int $storeId): string
    {
        /** @var Relation $relationModel */
        $relationModel = $this->relationFactory->create();
        $relationModel->setData([
            'tag_id' => (int)$this->getId(),
            'store_id' => $storeId,
            'product_id' => $productId,
            'customer_id' => $customerId,
            'active' => Relation::STATUS_ACTIVE,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);

        $result = '';
        $relationModelSaveNeed = false;

        switch ((int)$this->getStatus()) {
            case self::STATUS_APPROVED:
                if ($this->checkLinkBetweenTagProduct($relationModel)) {
                    $relation = $this->getLinkBetweenTagCustomerProduct($relationModel);
                    if ($relation->getId()) {
                        if (!$relation->getData('active')) {
                            // Reactivate the inactive relation
                            $relationModel->setData('tag_relation_id', $relation->getId());
                            $relationModelSaveNeed = true;
                        }
                    } else {
                        $relationModelSaveNeed = true;
                    }
                    $result = self::ADD_STATUS_EXIST;
                } else {
                    $relationModelSaveNeed = true;
                    $result = self::ADD_STATUS_SUCCESS;
                }
                break;

            case self::STATUS_PENDING:
                $relation = $this->getLinkBetweenTagCustomerProduct($relationModel);
                if ($relation->getId()) {
                    if (!$relation->getData('active')) {
                        $relationModel->setData('tag_relation_id', $relation->getId());
                        $relationModelSaveNeed = true;
                    }
                } else {
                    $relationModelSaveNeed = true;
                }
                $result = self::ADD_STATUS_NEW;
                break;

            case self::STATUS_DISABLED:
                if ($this->checkLinkBetweenTagCustomerProduct($relationModel)) {
                    $result = self::ADD_STATUS_REJECTED;
                } else {
                    $this->setStatus(self::STATUS_PENDING);
                    $this->save();
                    $relationModelSaveNeed = true;
                    $result = self::ADD_STATUS_NEW;
                }
                break;
        }

        if ($relationModelSaveNeed) {
            $relationModel->save();
        }

        return $result;
    }

    /**
     * Check whether product is already tagged in store (by any customer)
     *
     * @param Relation $relationModel
     * @return bool
     */
    private function checkLinkBetweenTagProduct(Relation $relationModel): bool
    {
        $customerId = $relationModel->getData('customer_id');
        $relationModel->setData('customer_id', null);
        $result = in_array($relationModel->getData('product_id'), $relationModel->getProductIds());
        $relationModel->setData('customer_id', $customerId);
        return $result;
    }

    /**
     * Check whether product is already tagged in store by this customer
     *
     * @param Relation $relationModel
     * @return bool
     */
    private function checkLinkBetweenTagCustomerProduct(Relation $relationModel): bool
    {
        return count($this->getLinkBetweenTagCustomerProduct($relationModel)->getProductIds()) > 0;
    }

    /**
     * Get relation model for product tagged in store by customer
     *
     * @param Relation $relationModel
     * @return Relation
     */
    private function getLinkBetweenTagCustomerProduct(Relation $relationModel): Relation
    {
        /** @var Relation $lookup */
        $lookup = $this->relationFactory->create();
        $lookup->loadByTagCustomerProduct(
            (int)$this->getId(),
            (int)$relationModel->getData('customer_id'),
            (int)$relationModel->getData('product_id'),
            (int)$relationModel->getData('store_id')
        );
        return $lookup;
    }

    /**
     * Rebuild tag summary/aggregate data
     *
     * @return $this
     */
    public function aggregate(): self
    {
        $this->_getResource()->aggregate($this);
        return $this;
    }

    /**
     * Check if tag is available in the specified store
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isAvailableInStore(?int $storeId = null): bool
    {
        if ($storeId === null) {
            return true;
        }
        return in_array($storeId, $this->getVisibleInStoreIds() ?? []);
    }

    /**
     * Truncate name before save
     *
     * @return $this
     */
    public function beforeSave(): self
    {
        $name = $this->getName();
        if ($name !== null) {
            $this->setName(mb_substr($name, 0, 255));
        }
        return parent::beforeSave();
    }

    /**
     * Load tag by name
     *
     * @param string $name
     * @return $this
     */
    public function loadByName(string $name): self
    {
        $this->_getResource()->loadByName($this, $name);
        return $this;
    }

    /**
     * Processing object after save data — handle assigned products
     *
     * @return $this
     */
    public function afterSave(): self
    {
        if ($this->hasData('tag_assigned_products')) {
            /** @var Relation $tagRelationModel */
            $tagRelationModel = $this->relationFactory->create();
            $tagRelationModel->addRelations(
                [$this->getTagId()],
                (array)$this->getDataByKey('tag_assigned_products'),
                0,
                (int)$this->getData('store')
            );
        }

        return parent::afterSave();
    }
}
