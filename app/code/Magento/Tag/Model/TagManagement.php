<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Model;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tag\Api\Data\TagInterface;
use Magento\Tag\Api\TagManagementInterface;
use Magento\Tag\Helper\Data as TagHelper;
use Magento\Tag\Model\ResourceModel\Tag as TagResource;

/**
 * Customer-facing tag submission service.
 *
 * Ports the legacy Mage_Tag_IndexController::saveAction flow into a service contract:
 * for each submitted tag name, loads an existing tag (if any), creates a PENDING tag
 * otherwise, then records the customer/product/store relation via Tag::saveRelation()
 * and aggregates summary rows for any relation change (SUCCESS or NEW).
 */
class TagManagement implements TagManagementInterface
{
    /**
     * @param TagFactory $tagFactory
     * @param TagResource $tagResource
     * @param TagHelper $tagHelper
     * @param CustomerSession $customerSession
     * @param StoreManagerInterface $storeManager
     * @param UserContextInterface $userContext
     */
    public function __construct(
        private readonly TagFactory $tagFactory,
        private readonly TagResource $tagResource,
        private readonly TagHelper $tagHelper,
        private readonly CustomerSession $customerSession,
        private readonly StoreManagerInterface $storeManager,
        private readonly UserContextInterface $userContext
    ) {
    }

    /**
     * Add tags to a product.
     *
     * Returns an associative array keyed by tag name whose values are the Tag::ADD_STATUS_*
     * strings returned by saveRelation() for that tag.
     *
     * @param int $productId
     * @param string[] $tagNames
     * @return string[]
     * @throws AuthorizationException
     * @throws CouldNotSaveException
     */
    public function addTagsToProduct($productId, array $tagNames)
    {
        $productId = (int)$productId;
        $customerId = $this->resolveCustomerId();
        $storeId = (int)$this->storeManager->getStore()->getId();

        $cleanedTagNames = $this->tagHelper->cleanTags($tagNames);

        $results = [];
        foreach ($cleanedTagNames as $tagName) {
            /** @var Tag $tagModel */
            $tagModel = $this->tagFactory->create();
            $tagModel->loadByName($tagName);

            if (!$tagModel->getId()) {
                $tagModel->setName($tagName);
                $tagModel->setStatus(TagInterface::STATUS_PENDING);
                $tagModel->setFirstCustomerId($customerId);
                $tagModel->setFirstStoreId($storeId);
                try {
                    $this->tagResource->save($tagModel);
                } catch (\Exception $exception) {
                    throw new CouldNotSaveException(
                        __($exception->getMessage()),
                        $exception
                    );
                }
            }

            $status = $tagModel->saveRelation($customerId, $productId, $storeId);

            if ($status === Tag::ADD_STATUS_SUCCESS || $status === Tag::ADD_STATUS_NEW) {
                $tagModel->aggregate();
            }

            $results[$tagName] = $status;
        }

        return $results;
    }

    /**
     * Resolve the submitting customer id.
     *
     * REST calls present the customer via the webapi UserContext (bearer token);
     * frontend controller calls present it via the customer session. The REST
     * context is authoritative when both are present because the frontend session
     * is not populated inside a stateless REST request.
     *
     * @return int
     * @throws AuthorizationException
     */
    private function resolveCustomerId(): int
    {
        if ((int)$this->userContext->getUserType() === UserContextInterface::USER_TYPE_CUSTOMER) {
            $userId = (int)$this->userContext->getUserId();
            if ($userId > 0) {
                return $userId;
            }
        }

        $sessionCustomerId = (int)$this->customerSession->getCustomerId();
        if ($sessionCustomerId > 0) {
            return $sessionCustomerId;
        }

        throw new AuthorizationException(__('Customer authentication is required to tag a product.'));
    }
}
