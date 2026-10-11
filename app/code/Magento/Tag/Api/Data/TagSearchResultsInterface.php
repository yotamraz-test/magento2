<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
namespace Magento\Tag\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Interface for tag search results.
 * @api
 * @since 100.0.2
 */
interface TagSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get tags list.
     *
     * @return \Magento\Tag\Api\Data\TagInterface[]
     */
    public function getItems();

    /**
     * Set tags list.
     *
     * @param \Magento\Tag\Api\Data\TagInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
