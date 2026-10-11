<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
namespace Magento\Tag\Api;

/**
 * Tag management interface for customer-facing tag submission.
 * @api
 * @since 100.0.2
 */
interface TagManagementInterface
{
    /**
     * Add tags to a product.
     *
     * Accepts a product ID and an array of tag name strings.
     * Returns an array of status strings per tag.
     *
     * @param int $productId
     * @param string[] $tagNames
     * @return string[]
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function addTagsToProduct($productId, array $tagNames);
}
