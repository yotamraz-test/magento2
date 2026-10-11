<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
namespace Magento\Tag\Api\Data;

/**
 * Tag interface.
 * @api
 * @since 100.0.2
 */
interface TagInterface
{
    /**#@+
     * Constants for keys of data array. Identical to the name of the getter in snake case
     */
    const TAG_ID = 'tag_id';
    const NAME = 'name';
    const STATUS = 'status';
    const FIRST_CUSTOMER_ID = 'first_customer_id';
    const FIRST_STORE_ID = 'first_store_id';
    /**#@-*/

    /**#@+
     * Tag statuses
     */
    const STATUS_DISABLED = -1;
    const STATUS_PENDING = 0;
    const STATUS_APPROVED = 1;
    /**#@-*/

    /**
     * Get tag ID
     *
     * @return int|null
     */
    public function getTagId();

    /**
     * Set tag ID
     *
     * @param int $tagId
     * @return TagInterface
     */
    public function setTagId($tagId);

    /**
     * Get tag name
     *
     * @return string|null
     */
    public function getName();

    /**
     * Set tag name
     *
     * @param string $name
     * @return TagInterface
     */
    public function setName($name);

    /**
     * Get tag status
     *
     * @return int|null
     */
    public function getStatus();

    /**
     * Set tag status
     *
     * @param int $status
     * @return TagInterface
     */
    public function setStatus($status);

    /**
     * Get first customer ID
     *
     * @return int|null
     */
    public function getFirstCustomerId();

    /**
     * Set first customer ID
     *
     * @param int|null $customerId
     * @return TagInterface
     */
    public function setFirstCustomerId($customerId);

    /**
     * Get first store ID
     *
     * @return int|null
     */
    public function getFirstStoreId();

    /**
     * Set first store ID
     *
     * @param int|null $storeId
     * @return TagInterface
     */
    public function setFirstStoreId($storeId);
}
